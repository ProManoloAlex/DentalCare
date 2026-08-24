<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

/**
 * IMPORTANTE: no existe una tabla "facturas" en la base de datos.
 * Esta clase construye "facturas virtuales" combinando:
 *   - cada tratamiento con costo_total > 0  (folio "T-{id}")
 *   - cada cita con costo > 0               (folio "C-{id}")
 * y calculando su saldo restando lo que ya se registró en "pagos".
 * Es de solo lectura: aquí no se "crea" una factura, se crean
 * tratamientos/citas desde sus propios módulos.
 */
class FacturaRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listarDerivadas(?string $busqueda, ?string $estado): array {
        $query = "
            SELECT * FROM (
                SELECT
                    CONCAT('T-', t.id) AS folio, 'tratamiento' AS origen, t.id AS origen_id,
                    up.nombre AS paciente, ud.nombre AS doctor, t.nombre AS concepto,
                    t.fecha_inicio AS fecha, t.costo_total AS monto,
                    COALESCE((SELECT SUM(monto) FROM pagos WHERE tratamiento_id = t.id), 0) AS pagado
                FROM tratamientos t
                JOIN pacientes p ON t.paciente_id = p.id
                JOIN usuarios up ON p.usuario_id = up.id
                JOIN doctores d ON t.doctor_id = d.id
                JOIN usuarios ud ON d.usuario_id = ud.id
                WHERE t.estado != 'cancelado' AND t.costo_total > 0

                UNION ALL

                SELECT
                    CONCAT('C-', c.id), 'cita', c.id,
                    up.nombre, ud.nombre, s.nombre,
                    c.fecha, c.costo,
                    COALESCE((SELECT SUM(monto) FROM pagos WHERE cita_id = c.id), 0)
                FROM citas c
                JOIN pacientes p ON c.paciente_id = p.id
                JOIN usuarios up ON p.usuario_id = up.id
                JOIN doctores d ON c.doctor_id = d.id
                JOIN usuarios ud ON d.usuario_id = ud.id
                JOIN servicios s ON c.servicio_id = s.id
                WHERE c.estado != 'cancelada' AND c.costo > 0
            ) AS facturas";

        $parametros = [];
        if ($busqueda) {
            $query .= " WHERE paciente LIKE ?";
            $parametros[] = "%$busqueda%";
        }
        $query .= " ORDER BY fecha DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        $filas = $stmt->fetchAll();

        // El estado (Pagado / Pago Parcial / Pendiente) depende de monto vs.
        // pagado, así que se calcula aquí en PHP en vez de en SQL -- mantiene
        // la query legible y evita repetir el mismo CASE dos veces.
        // Nota: no existe un campo de "fecha de vencimiento" en tratamientos
        // ni citas, así que por ahora no hay estado "Vencido". Si más adelante
        // quieres esa regla (ej. vencido a los 30 días), aquí es donde se agrega.
        $resultado = [];
        foreach ($filas as $fila) {
            $saldo = (float) $fila['monto'] - (float) $fila['pagado'];
            $fila['saldo'] = $saldo;
            $fila['estado'] = $saldo <= 0 ? 'pagado' : ((float) $fila['pagado'] > 0 ? 'parcial' : 'pendiente');

            if ($estado && $fila['estado'] !== $estado) {
                continue;
            }
            $resultado[] = $fila;
        }
        return $resultado;
    }

    public function obtenerResumen(): array {
        $facturas = $this->listarDerivadas(null, null);

        $totalFacturado = array_sum(array_column($facturas, 'monto'));
        $totalCobrado = array_sum(array_map(
            fn($f) => min((float) $f['monto'], (float) $f['pagado']),
            $facturas
        ));

        return [
            'total_facturas' => count($facturas),
            'total_facturado' => $totalFacturado,
            'total_cobrado' => $totalCobrado,
            'total_pendiente' => $totalFacturado - $totalCobrado,
        ];
    }
}