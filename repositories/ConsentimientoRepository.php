<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class ConsentimientoRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listar(?string $busqueda, ?string $estado): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "(up.nombre LIKE ? OR c.titulo LIKE ?)";
            $like = "%$busqueda%";
            array_push($parametros, $like, $like);
        }
        if ($estado) {
            $condiciones[] = "c.estado = ?";
            $parametros[] = $estado;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $query = "SELECT
                    c.id, c.tipo, c.titulo, c.estado, c.firma, c.firmado_por, c.fecha, c.fecha_firma,
                    up.nombre AS paciente_nombre
                  FROM consentimientos c
                  JOIN pacientes p ON c.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  $where
                  ORDER BY c.fecha DESC, c.id DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array {
        $query = "SELECT c.*, up.nombre AS paciente_nombre, ud.nombre AS doctor_nombre
                  FROM consentimientos c
                  JOIN pacientes p ON c.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN doctores d ON c.doctor_id = d.id
                  JOIN usuarios ud ON d.usuario_id = ud.id
                  WHERE c.id = ?";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /**
     * Para el portal del paciente: solo SUS consentimientos, filtrados
     * en la query misma (no en PHP) para que no exista ninguna manera
     * de que un paciente vea los de otro.
     */
    public function listarPorPaciente(int $pacienteId): array {
        $query = "SELECT c.id, c.tipo, c.titulo, c.texto, c.estado, c.firma, c.firmado_por, c.fecha, c.fecha_firma,
                    ud.nombre AS doctor_nombre
                  FROM consentimientos c
                  JOIN doctores d ON c.doctor_id = d.id
                  JOIN usuarios ud ON d.usuario_id = ud.id
                  WHERE c.paciente_id = ?
                  ORDER BY c.fecha DESC, c.id DESC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        return $stmt->fetchAll();
    }

    public function contarPorEstado(): array {
        $filas = $this->conexion->query("SELECT estado, COUNT(*) AS total FROM consentimientos GROUP BY estado")->fetchAll();
        $conteo = ['pendiente' => 0, 'firmado' => 0, 'rechazado' => 0];
        foreach ($filas as $fila) {
            $conteo[$fila['estado']] = (int) $fila['total'];
        }
        return $conteo;
    }

    /**
     * El consentimiento más reciente ligado a un tratamiento -- lo usa
     * CitaService para saber si ya está firmado antes de dejar completar
     * una cita de ese tratamiento.
     */
    public function obtenerPorTratamiento(int $tratamientoId): ?array {
        $stmt = $this->conexion->prepare(
            "SELECT id, estado FROM consentimientos WHERE tratamiento_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$tratamientoId]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    public function crear(array $datos): int {
        $query = "INSERT INTO consentimientos (paciente_id, tratamiento_id, doctor_id, tipo, titulo, texto, estado, fecha)
                  VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['pacienteId'], $datos['tratamientoId'] ?? null, $datos['doctorId'], $datos['tipo'],
            $datos['titulo'], $datos['texto'], $datos['fecha'],
        ]);
        return (int) $this->conexion->lastInsertId();
    }


    public function firmar(int $id, string $nuevoEstado, ?string $firma, string $firmadoPor): void {
        $query = "UPDATE consentimientos SET estado = ?, firma = ?, firmado_por = ?, fecha_firma = NOW() WHERE id = ?";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$nuevoEstado, $firma, $firmadoPor, $id]);
    }
}