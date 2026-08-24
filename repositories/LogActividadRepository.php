<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class LogActividadRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listarRecientes(int $limite, ?string $modulo): array {
        $condiciones = [];
        $parametros = [];

        if ($modulo) {
            $condiciones[] = "l.modulo = ?";
            $parametros[] = $modulo;
        }
        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $query = "SELECT l.id, l.modulo, l.accion, l.detalle, l.fecha_creacion, u.nombre AS usuario_nombre
                  FROM log_actividad l
                  LEFT JOIN usuarios u ON l.usuario_id = u.id
                  $where
                  ORDER BY l.fecha_creacion DESC
                  LIMIT " . (int) $limite;

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function listarModulosDisponibles(): array {
        $stmt = $this->conexion->query("SELECT DISTINCT modulo FROM log_actividad ORDER BY modulo ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
