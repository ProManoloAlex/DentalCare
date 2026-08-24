<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class TratamientoRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    /**
     * Trae los tratamientos activos con su saldo calculado en vivo
     * (costo_total - SUM(pagos.monto)), nunca un campo guardado.
     */
    public function obtenerActivos(int $pacienteId): array {
        $query = "SELECT 
                    t.id, t.nombre, t.categoria, t.descripcion,
                    t.sesiones_totales, t.sesiones_completadas,
                    t.fecha_inicio, t.fecha_fin_estimada, t.costo_total,
                    u.nombre AS doctor_nombre,
                    COALESCE(SUM(p.monto), 0) AS monto_pagado
                  FROM tratamientos t
                  JOIN doctores d ON t.doctor_id = d.id
                  JOIN usuarios u ON d.usuario_id = u.id
                  LEFT JOIN pagos p ON p.tratamiento_id = t.id
                  WHERE t.paciente_id = ? AND t.estado = 'activo'
                  GROUP BY t.id
                  ORDER BY t.fecha_inicio DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        return $stmt->fetchAll();
    }

    public function contarActivos(int $pacienteId): int {
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM tratamientos WHERE paciente_id = ? AND estado = 'activo'"
        );
        $stmt->execute([$pacienteId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Saldo pendiente de TODOS los tratamientos no cancelados
     * (costo_total - lo pagado), sumado.
     */
    public function calcularSaldoPendiente(int $pacienteId): float {
        $query = "SELECT COALESCE(SUM(t.costo_total), 0) - COALESCE(SUM(p.monto), 0)
                  FROM tratamientos t
                  LEFT JOIN pagos p ON p.tratamiento_id = t.id
                  WHERE t.paciente_id = ? AND t.estado != 'cancelado'";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Detalle por tratamiento para "Mi Cuenta": solo los que
     * todavía tienen saldo pendiente > 0.
     */
    public function obtenerDetalleConSaldo(int $pacienteId): array {
        $query = "SELECT 
                    t.id, t.nombre, t.costo_total,
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

    /**
     * Historial de pagos individuales de un tratamiento
     * (esto es lo que responde a "qué ha ido pagando y cuándo").
     */
    // ============================================================
    // MÉTODOS "ADMIN" — ven/crean tratamientos de TODOS los pacientes,
    // no filtrados por un solo paciente.
    // ============================================================

    public function obtenerTodosAdmin(?string $busqueda, ?string $estado, string $orden): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "(up.nombre LIKE ? OR t.nombre LIKE ? OR ud.nombre LIKE ?)";
            $like = "%$busqueda%";
            array_push($parametros, $like, $like, $like);
        }
        if ($estado) {
            $condiciones[] = "t.estado = ?";
            $parametros[] = $estado;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
        $ordenSql = $orden === 'antiguos' ? 't.fecha_inicio ASC' : 't.fecha_inicio DESC';

        $query = "SELECT 
                    t.id, t.nombre, t.categoria, t.fecha_inicio, t.estado, t.doctor_id,
                    t.sesiones_totales, t.sesiones_completadas, t.costo_total,
                    p.id AS paciente_id, up.nombre AS paciente_nombre,
                    ud.nombre AS doctor_nombre,
                    COALESCE((SELECT SUM(monto) FROM pagos WHERE tratamiento_id = t.id), 0) AS monto_pagado,
                    (SELECT c.servicio_id FROM citas c WHERE c.tratamiento_id = t.id ORDER BY c.fecha DESC, c.hora DESC LIMIT 1) AS servicio_id,
                    (SELECT CONCAT(c.fecha, ' ', c.hora) FROM citas c WHERE c.tratamiento_id = t.id AND c.estado IN ('pendiente','confirmada') AND c.fecha >= CURDATE() ORDER BY c.fecha ASC, c.hora ASC LIMIT 1) AS proxima_cita
                  FROM tratamientos t
                  JOIN pacientes p ON t.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN doctores d ON t.doctor_id = d.id
                  JOIN usuarios ud ON d.usuario_id = ud.id
                  $where
                  ORDER BY $ordenSql";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtenerResumenAdmin(): array {
        $total = (int) $this->conexion->query("SELECT COUNT(*) FROM tratamientos")->fetchColumn();
        $activos = (int) $this->conexion->query("SELECT COUNT(*) FROM tratamientos WHERE estado = 'activo'")->fetchColumn();
        $completados = (int) $this->conexion->query("SELECT COUNT(*) FROM tratamientos WHERE estado = 'completado'")->fetchColumn();

        $saldo = (float) $this->conexion->query(
            "SELECT COALESCE(SUM(t.costo_total), 0) - COALESCE((SELECT SUM(monto) FROM pagos WHERE tratamiento_id IN (SELECT id FROM tratamientos WHERE estado != 'cancelado')), 0)
             FROM tratamientos t WHERE t.estado != 'cancelado'"
        )->fetchColumn();

        return ['total' => $total, 'activos' => $activos, 'completados' => $completados, 'saldo_pendiente' => $saldo];
    }

    public function crearAdmin(array $datos): int {
            $query = "INSERT INTO tratamientos 
                        (paciente_id, doctor_id, nombre, categoria, descripcion, diagnostico, notas,
                        sesiones_totales, fecha_inicio, costo_total, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')";

            $stmt = $this->conexion->prepare($query);
            $stmt->execute([
                $datos['pacienteId'], $datos['doctorId'], $datos['nombre'], $datos['categoria'],
                $datos['descripcion'], $datos['diagnostico'], $datos['notas'],
                $datos['sesionesTotal'], $datos['fechaInicio'], $datos['costoTotal'],
            ]);

            $tratamientoId = (int) $this->conexion->lastInsertId();

            if ($datos['pagoInicial'] > 0) {
                $stmtPago = $this->conexion->prepare(
                    "INSERT INTO pagos (paciente_id, tratamiento_id, monto, metodo, fecha_pago) VALUES (?, ?, ?, 'efectivo', ?)"
                );
                $stmtPago->execute([$datos['pacienteId'], $tratamientoId, $datos['pagoInicial'], $datos['fechaInicio']]);
            }

            return $tratamientoId;
        }
        
    /**
     * Se llama cuando una CITA vinculada a este tratamiento se marca
     * "completada" -- suma 1 sesión y, si llega al total, el tratamiento
     * pasa solo a "completado". Un solo UPDATE atómico para evitar
     * condiciones de carrera si dos citas se completan casi a la vez.
     */
    public function sumarSesionCompletada(int $tratamientoId): void {
        $query = "UPDATE tratamientos
                  SET sesiones_completadas = sesiones_completadas + 1,
                      estado = CASE 
                                  WHEN sesiones_completadas + 1 >= sesiones_totales THEN 'completado' 
                                  ELSE estado 
                               END
                  WHERE id = ? AND estado = 'activo'";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$tratamientoId]);
    }

    public function cancelarAdmin(int $tratamientoId): void {
        $stmt = $this->conexion->prepare("UPDATE tratamientos SET estado = 'cancelado' WHERE id = ?");
        $stmt->execute([$tratamientoId]);
    }

    public function obtenerPagosDeTratamiento(int $tratamientoId, int $pacienteId): array {
        $query = "SELECT monto, metodo, fecha_pago 
                  FROM pagos 
                  WHERE tratamiento_id = ? AND paciente_id = ?
                  ORDER BY fecha_pago DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$tratamientoId, $pacienteId]);
        return $stmt->fetchAll();
    }
    
    public function obtenerHistorialPorPaciente(int $pacienteId): array {
        $query = "SELECT 
                    t.id, t.nombre, t.categoria, t.diagnostico,
                    t.sesiones_totales, t.sesiones_completadas,
                    t.fecha_inicio, t.costo_total, t.estado,
                    u.nombre AS doctor_nombre,
                    COALESCE(SUM(p.monto), 0) AS monto_pagado
                  FROM tratamientos t
                  JOIN doctores d ON t.doctor_id = d.id
                  JOIN usuarios u ON d.usuario_id = u.id
                  LEFT JOIN pagos p ON p.tratamiento_id = t.id
                  WHERE t.paciente_id = ?
                  GROUP BY t.id
                  ORDER BY t.fecha_inicio DESC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        return $stmt->fetchAll();
    }
}