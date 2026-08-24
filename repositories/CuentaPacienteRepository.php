<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

/**
 * El saldo total considera DOS fuentes, igual que ya lo calcula
 * PacienteRepository del lado admin:
 *   1. Tratamientos no cancelados: costo_total - pagos ligados a ellos
 *   2. Citas sueltas (sin tratamiento) que no se han marcado como pagadas
 * TratamientoRepository::calcularSaldoPendiente() del lado admin SOLO
 * cubre el punto 1 -- por eso aquí se calculan ambos, para no
 * subestimarle el saldo real al paciente en su propio portal.
 */
class CuentaPacienteRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function calcularSaldoTotal(int $pacienteId): float {
        $stmt = $this->conexion->prepare(
            "SELECT
                COALESCE((SELECT SUM(t.costo_total) FROM tratamientos t WHERE t.paciente_id = ? AND t.estado != 'cancelado'), 0)
                - COALESCE((SELECT SUM(pg.monto) FROM pagos pg JOIN tratamientos t2 ON pg.tratamiento_id = t2.id WHERE t2.paciente_id = ?), 0)
                + COALESCE((SELECT SUM(c.costo) FROM citas c WHERE c.paciente_id = ? AND c.pagado = 0 AND c.estado != 'cancelada'), 0)
            "
        );
        $stmt->execute([$pacienteId, $pacienteId, $pacienteId]);
        return (float) $stmt->fetchColumn();
    }

    /** Tratamientos con saldo pendiente > 0. */
    public function obtenerTratamientosConSaldo(int $pacienteId): array {
        $query = "SELECT t.id, t.nombre, t.costo_total,
                    COALESCE(SUM(p.monto), 0) AS monto_pagado
                  FROM tratamientos t
                  LEFT JOIN pagos p ON p.tratamiento_id = t.id
                  WHERE t.paciente_id = ? AND t.estado != 'cancelado'
                  GROUP BY t.id
                  HAVING (t.costo_total - COALESCE(SUM(p.monto), 0)) > 0
                  ORDER BY t.fecha_inicio DESC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        return $stmt->fetchAll();
    }

    /** Citas sueltas (no ligadas a un tratamiento) que no se han pagado. */
    public function obtenerCitasSinPagar(int $pacienteId): array {
        $query = "SELECT c.id, s.nombre, c.costo, c.fecha
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.paciente_id = ? AND c.pagado = 0 AND c.estado != 'cancelada' AND c.costo > 0
                  ORDER BY c.fecha DESC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        return $stmt->fetchAll();
    }
}
