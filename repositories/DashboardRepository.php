<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class DashboardRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function contarCitasEnRango(string $fechaInicio, string $fechaFin): int {
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM citas WHERE fecha BETWEEN ? AND ? AND estado != 'cancelada'"
        );
        $stmt->execute([$fechaInicio, $fechaFin]);
        return (int) $stmt->fetchColumn();
    }

    public function contarPacientesActivos(): int {
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM pacientes p 
             JOIN usuarios u ON p.usuario_id = u.id 
             WHERE u.activo = 1"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function calcularIngresosEnRango(string $fechaInicio, string $fechaFin): float {
        $stmt = $this->conexion->prepare(
            "SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE fecha_pago BETWEEN ? AND ?"
        );
        $stmt->execute([$fechaInicio, $fechaFin]);
        return (float) $stmt->fetchColumn();
    }

    public function contarCitasPendientes(): int {
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM citas WHERE estado = 'pendiente'"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function obtenerIngresosMensuales(int $meses): array {
        $query = "SELECT 
                    DATE_FORMAT(fecha_pago, '%Y-%m') AS mes_key,
                    SUM(monto) AS total
                  FROM pagos
                  WHERE fecha_pago >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                  GROUP BY mes_key
                  ORDER BY mes_key ASC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$meses]);
        return $stmt->fetchAll();
    }

    public function obtenerCitasEnRango(string $fechaInicio, string $fechaFin): array {
        $query = "SELECT 
                    c.id, c.hora, c.estado,
                    up.nombre AS paciente_nombre,
                    ud.nombre AS doctor_nombre,
                    s.nombre AS servicio_nombre
                  FROM citas c
                  JOIN pacientes p ON c.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN doctores d ON c.doctor_id = d.id
                  JOIN usuarios ud ON d.usuario_id = ud.id
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.fecha BETWEEN ? AND ? AND c.estado != 'cancelada'
                  ORDER BY c.fecha ASC, c.hora ASC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$fechaInicio, $fechaFin]);
        return $stmt->fetchAll();
    }

    public function obtenerPagosRecientes(int $limite): array {
        $query = "SELECT 
                    pg.monto, pg.metodo, pg.fecha_pago,
                    up.nombre AS paciente_nombre,
                    t.nombre AS tratamiento_nombre
                  FROM pagos pg
                  JOIN pacientes p ON pg.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  LEFT JOIN tratamientos t ON pg.tratamiento_id = t.id
                  ORDER BY pg.fecha_creacion DESC
                  LIMIT ?";

        $stmt = $this->conexion->prepare($query);
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function obtenerServiciosMasSolicitados(int $limite): array {
        // "Más solicitados este mes" = servicios con más citas creadas este mes
        $query = "SELECT s.nombre, COUNT(*) AS total
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE MONTH(c.fecha_creacion) = MONTH(CURDATE())
                    AND YEAR(c.fecha_creacion) = YEAR(CURDATE())
                    AND c.estado != 'cancelada'
                  GROUP BY s.id, s.nombre
                  ORDER BY total DESC
                  LIMIT ?";

        $stmt = $this->conexion->prepare($query);
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}