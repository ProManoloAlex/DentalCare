<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class OdontogramaRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    /**
     * Si el paciente nunca ha tenido nada registrado, esto regresa
     * un array vacío — el Service es quien completa los 32 dientes
     * en "sano" por default. Así no ensuciamos la tabla con filas
     * de pacientes que nunca han usado este módulo.
     */
    public function obtenerPorPaciente(int $pacienteId): array {
        $stmt = $this->conexion->prepare(
            "SELECT numero_diente, condicion, notas FROM odontograma WHERE paciente_id = ?"
        );
        $stmt->execute([$pacienteId]);
        return $stmt->fetchAll();
    }

    public function obtenerUltimaActualizacion(int $pacienteId): ?string {
        $stmt = $this->conexion->prepare(
            "SELECT MAX(fecha_actualizacion) FROM odontograma WHERE paciente_id = ?"
        );
        $stmt->execute([$pacienteId]);
        $fecha = $stmt->fetchColumn();
        return $fecha ?: null;
    }

    /**
     * Guarda los 32 dientes de un jalón. Usa "upsert" (INSERT ... ON
     * DUPLICATE KEY UPDATE) para que no importe si esa fila ya existía
     * o es la primera vez que se guarda algo de este paciente.
     */
    public function guardarCompleto(int $pacienteId, array $dientes): void {
        $query = "INSERT INTO odontograma (paciente_id, numero_diente, condicion, notas)
                  VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE condicion = VALUES(condicion), notas = VALUES(notas)";

        $stmt = $this->conexion->prepare($query);

        $this->conexion->beginTransaction();
        try {
            foreach ($dientes as $numero => $datos) {
                $stmt->execute([$pacienteId, $numero, $datos['condicion'], $datos['notas'] ?: null]);
            }
            $this->conexion->commit();
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    /**
     * Lista de pacientes con la fecha de su última actualización
     * (o null si nunca se les ha llenado el odontograma).
     */
    public function listarPacientesConResumen(?string $busqueda): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "u.nombre LIKE ?";
            $parametros[] = "%$busqueda%";
        }
        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $query = "SELECT p.id, u.nombre, MAX(o.fecha_actualizacion) AS ultima_actualizacion
                  FROM pacientes p
                  JOIN usuarios u ON p.usuario_id = u.id
                  LEFT JOIN odontograma o ON o.paciente_id = p.id
                  $where
                  GROUP BY p.id, u.nombre
                  ORDER BY u.nombre ASC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }
}