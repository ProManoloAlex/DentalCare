<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class RecetaRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listar(?string $busqueda, ?string $estado): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "(up.nombre LIKE ? OR r.folio LIKE ? OR r.diagnostico LIKE ?)";
            $like = "%$busqueda%";
            array_push($parametros, $like, $like, $like);
        }
        if ($estado) {
            $condiciones[] = "r.estado = ?";
            $parametros[] = $estado;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $query = "SELECT
                    r.id, r.folio, r.diagnostico, r.motivo_consulta, r.indicaciones,
                    r.proxima_cita, r.estado, r.fecha,
                    up.nombre AS paciente_nombre, ud.nombre AS doctor_nombre
                  FROM recetas r
                  JOIN pacientes p ON r.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN doctores d ON r.doctor_id = d.id
                  JOIN usuarios ud ON d.usuario_id = ud.id
                  $where
                  ORDER BY r.fecha DESC, r.id DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        $recetas = $stmt->fetchAll();

        if (!$recetas) {
            return [];
        }

        // Un solo query extra para traer TODOS los medicamentos de TODAS las
        // recetas encontradas, en vez de una consulta por receta (evita N+1).
        $ids = array_column($recetas, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtMeds = $this->conexion->prepare(
            "SELECT * FROM receta_medicamentos WHERE receta_id IN ($placeholders)"
        );
        $stmtMeds->execute($ids);

        $medsPorReceta = [];
        foreach ($stmtMeds->fetchAll() as $med) {
            $medsPorReceta[$med['receta_id']][] = $med;
        }

        foreach ($recetas as &$receta) {
            $receta['medicamentos'] = $medsPorReceta[$receta['id']] ?? [];
        }

        return $recetas;
    }

    /**
     * Para el portal del paciente: solo SUS recetas, filtradas en la
     * query misma -- para que no haya forma de ver las de otro paciente.
     */
    public function listarPorPaciente(int $pacienteId): array {
        $query = "SELECT
                    r.id, r.folio, r.diagnostico, r.motivo_consulta, r.indicaciones,
                    r.proxima_cita, r.estado, r.fecha,
                    ud.nombre AS doctor_nombre
                  FROM recetas r
                  JOIN doctores d ON r.doctor_id = d.id
                  JOIN usuarios ud ON d.usuario_id = ud.id
                  WHERE r.paciente_id = ?
                  ORDER BY r.fecha DESC, r.id DESC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        $recetas = $stmt->fetchAll();

        if (!$recetas) {
            return [];
        }

        $ids = array_column($recetas, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtMeds = $this->conexion->prepare(
            "SELECT * FROM receta_medicamentos WHERE receta_id IN ($placeholders)"
        );
        $stmtMeds->execute($ids);

        $medsPorReceta = [];
        foreach ($stmtMeds->fetchAll() as $med) {
            $medsPorReceta[$med['receta_id']][] = $med;
        }

        foreach ($recetas as &$receta) {
            $receta['medicamentos'] = $medsPorReceta[$receta['id']] ?? [];
        }

        return $recetas;
    }

    // ---------- Resumen ----------

    public function contarPorEstado(): array {
        $filas = $this->conexion->query("SELECT estado, COUNT(*) AS total FROM recetas GROUP BY estado")->fetchAll();
        $conteo = ['activa' => 0, 'completada' => 0, 'vencida' => 0];
        foreach ($filas as $fila) {
            $conteo[$fila['estado']] = (int) $fila['total'];
        }
        return $conteo;
    }

    public function contarEmitidasEsteMes(): int {
        return (int) $this->conexion->query(
            "SELECT COUNT(*) FROM recetas WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())"
        )->fetchColumn();
    }

    public function contarPacientesUnicos(): int {
        return (int) $this->conexion->query("SELECT COUNT(DISTINCT paciente_id) FROM recetas")->fetchColumn();
    }

    public function contarTotalMedicamentos(): int {
        return (int) $this->conexion->query("SELECT COUNT(*) FROM receta_medicamentos")->fetchColumn();
    }

    public function obtenerActivasRecientes(int $limite): array {
        $query = "SELECT r.id, r.folio, r.diagnostico, r.fecha, up.nombre AS paciente_nombre
                  FROM recetas r
                  JOIN pacientes p ON r.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  WHERE r.estado = 'activa'
                  ORDER BY r.fecha DESC
                  LIMIT ?";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function obtenerMedicamentosMasPrescritos(int $limite): array {
        $query = "SELECT nombre, COUNT(*) AS total
                  FROM receta_medicamentos
                  GROUP BY nombre
                  ORDER BY total DESC
                  LIMIT ?";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ---------- Crear / actualizar estado ----------

    private function generarFolio(): string {
        $anio = date('Y');
        $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM recetas WHERE folio LIKE ?");
        $stmt->execute(["RX-$anio-%"]);
        $consecutivo = (int) $stmt->fetchColumn() + 1;
        return sprintf('RX-%s-%04d', $anio, $consecutivo);
    }

    /**
     * @param array $medicamentos cada elemento: ['nombre','concentracion','forma','dosis','frecuencia','duracion','instrucciones']
     */
    public function crear(array $datos, array $medicamentos): int {
        if (empty($medicamentos)) {
            throw new InvalidArgumentException('La receta necesita al menos un medicamento.');
        }

        $this->conexion->beginTransaction();
        try {
            $folio = $this->generarFolio();

            $stmtReceta = $this->conexion->prepare(
                "INSERT INTO recetas (folio, paciente_id, doctor_id, diagnostico, motivo_consulta, indicaciones, proxima_cita, estado, fecha)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'activa', ?)"
            );
            $stmtReceta->execute([
                $folio, $datos['pacienteId'], $datos['doctorId'], $datos['diagnostico'],
                $datos['motivoConsulta'] ?: null, $datos['indicaciones'] ?: null,
                $datos['proximaCita'] ?: null, $datos['fecha'],
            ]);
            $recetaId = (int) $this->conexion->lastInsertId();

            $stmtMed = $this->conexion->prepare(
                "INSERT INTO receta_medicamentos (receta_id, nombre, concentracion, forma, dosis, frecuencia, duracion, instrucciones)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($medicamentos as $med) {
                $stmtMed->execute([
                    $recetaId, $med['nombre'], $med['concentracion'] ?: null, $med['forma'] ?: null,
                    $med['dosis'], $med['frecuencia'] ?: null, $med['duracion'] ?: null, $med['instrucciones'] ?: null,
                ]);
            }

            $this->conexion->commit();
            return $recetaId;
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado(int $recetaId, string $nuevoEstado): void {
        $stmt = $this->conexion->prepare("UPDATE recetas SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevoEstado, $recetaId]);
    }

    /**
     * Reemplaza los medicamentos por completo (borra los viejos, inserta
     * los nuevos) -- más simple y confiable que intentar "hacer match"
     * entre la lista anterior y la nueva fila por fila. No toca "estado"
     * ni "fecha" (fecha de emisión original), solo el contenido editable.
     */
    public function actualizar(int $recetaId, array $datos, array $medicamentos): void {
        if (empty($medicamentos)) {
            throw new InvalidArgumentException('La receta necesita al menos un medicamento.');
        }

        $this->conexion->beginTransaction();
        try {
            $stmtReceta = $this->conexion->prepare(
                "UPDATE recetas SET paciente_id = ?, diagnostico = ?, motivo_consulta = ?, indicaciones = ?, proxima_cita = ?
                 WHERE id = ?"
            );
            $stmtReceta->execute([
                $datos['pacienteId'], $datos['diagnostico'], $datos['motivoConsulta'] ?: null,
                $datos['indicaciones'] ?: null, $datos['proximaCita'] ?: null, $recetaId,
            ]);

            $stmtBorrar = $this->conexion->prepare("DELETE FROM receta_medicamentos WHERE receta_id = ?");
            $stmtBorrar->execute([$recetaId]);

            $stmtMed = $this->conexion->prepare(
                "INSERT INTO receta_medicamentos (receta_id, nombre, concentracion, forma, dosis, frecuencia, duracion, instrucciones)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($medicamentos as $med) {
                $stmtMed->execute([
                    $recetaId, $med['nombre'], $med['concentracion'] ?: null, $med['forma'] ?: null,
                    $med['dosis'], $med['frecuencia'] ?: null, $med['duracion'] ?: null, $med['instrucciones'] ?: null,
                ]);
            }

            $this->conexion->commit();
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }
}