<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class PagoRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    /**
     * Listado de pagos ya registrados, con el nombre del paciente y
     * a qué se aplicó (tratamiento, cita, o "pago libre"). Esto NO
     * es la tabla "facturas" (esa se calcula aparte, en FacturaRepository) —
     * esto es el historial real de la tabla "pagos".
     */
    public function listarConDetalle(?string $busqueda, ?string $metodo): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "up.nombre LIKE ?";
            $parametros[] = "%$busqueda%";
        }
        if ($metodo) {
            $condiciones[] = "pg.metodo = ?";
            $parametros[] = $metodo;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $query = "SELECT
                    pg.id, pg.monto, pg.metodo, pg.fecha_pago,
                    up.nombre AS paciente_nombre,
                    COALESCE(t.nombre, s.nombre, 'Pago libre') AS concepto,
                    CASE
                        WHEN pg.tratamiento_id IS NOT NULL THEN CONCAT('T-', pg.tratamiento_id)
                        WHEN pg.cita_id IS NOT NULL THEN CONCAT('C-', pg.cita_id)
                        ELSE NULL
                    END AS referencia_factura
                  FROM pagos pg
                  JOIN pacientes p ON pg.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  LEFT JOIN tratamientos t ON pg.tratamiento_id = t.id
                  LEFT JOIN citas c ON pg.cita_id = c.id
                  LEFT JOIN servicios s ON c.servicio_id = s.id
                  $where
                  ORDER BY pg.fecha_pago DESC, pg.fecha_creacion DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtenerResumenMesActual(): array {
        $query = "SELECT metodo, COALESCE(SUM(monto), 0) AS total
                  FROM pagos
                  WHERE MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE())
                  GROUP BY metodo";
        $porMetodo = $this->conexion->query($query)->fetchAll();

        $total = (float) $this->conexion->query(
            "SELECT COALESCE(SUM(monto), 0) FROM pagos
             WHERE MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE())"
        )->fetchColumn();

        $cantidadEsteMes = (int) $this->conexion->query(
            "SELECT COUNT(*) FROM pagos
             WHERE MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE())"
        )->fetchColumn();

        return ['total_mes' => $total, 'por_metodo' => $porMetodo, 'cantidad_mes' => $cantidadEsteMes];
    }

    /**
     * Tratamientos activos con saldo > 0 y citas sin pagar de un
     * paciente. Es lo que llena el select "Aplicar a" del modal de
     * Registrar Pago — el doctor elige a qué se aplica el dinero
     * que le dieron (o lo deja como "pago libre").
     */
    public function obtenerSaldosDePaciente(int $pacienteId): array {
        $tratamientos = $this->conexion->prepare(
            "SELECT t.id, t.nombre AS descripcion,
                    t.costo_total - COALESCE((SELECT SUM(monto) FROM pagos WHERE tratamiento_id = t.id), 0) AS saldo
             FROM tratamientos t
             WHERE t.paciente_id = ? AND t.estado != 'cancelado'
             HAVING saldo > 0"
        );
        $tratamientos->execute([$pacienteId]);

        $citas = $this->conexion->prepare(
            "SELECT c.id, s.nombre AS descripcion, c.costo AS saldo
             FROM citas c
             JOIN servicios s ON c.servicio_id = s.id
             WHERE c.paciente_id = ? AND c.pagado = 0 AND c.estado != 'cancelada' AND c.costo > 0"
        );
        $citas->execute([$pacienteId]);

        $resultado = [];
        foreach ($tratamientos->fetchAll() as $fila) {
            $resultado[] = ['tipo' => 'tratamiento', 'id' => (int) $fila['id'], 'descripcion' => $fila['descripcion'], 'saldo' => (float) $fila['saldo']];
        }
        foreach ($citas->fetchAll() as $fila) {
            $resultado[] = ['tipo' => 'cita', 'id' => (int) $fila['id'], 'descripcion' => $fila['descripcion'], 'saldo' => (float) $fila['saldo']];
        }
        return $resultado;
    }

    /**
     * Registra el pago que el doctor capturó a mano. Si se aplicó a
     * una cita y con esto ya se cubrió su costo completo, la cita se
     * marca como pagada automáticamente.
     */
    public function crear(array $datos): int {
        $this->conexion->beginTransaction();
        try {
            $stmt = $this->conexion->prepare(
                "INSERT INTO pagos (paciente_id, tratamiento_id, cita_id, monto, metodo, fecha_pago)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $datos['pacienteId'],
                $datos['tratamientoId'] ?: null,
                $datos['citaId'] ?: null,
                $datos['monto'],
                $datos['metodo'],
                $datos['fechaPago'],
            ]);
            $pagoId = (int) $this->conexion->lastInsertId();

            if (!empty($datos['citaId'])) {
                $this->actualizarEstadoPagadoDeCita((int) $datos['citaId']);
            }

            $this->conexion->commit();
            return $pagoId;
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    private function actualizarEstadoPagadoDeCita(int $citaId): void {
        $stmt = $this->conexion->prepare(
            "SELECT c.costo, COALESCE((SELECT SUM(monto) FROM pagos WHERE cita_id = c.id), 0) AS pagado
             FROM citas c WHERE c.id = ?"
        );
        $stmt->execute([$citaId]);
        $fila = $stmt->fetch();

        if ($fila && (float) $fila['pagado'] >= (float) $fila['costo']) {
            $update = $this->conexion->prepare("UPDATE citas SET pagado = 1 WHERE id = ?");
            $update->execute([$citaId]);
        }
    }
}