<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class RecordatorioRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    /**
     * Recordatorios "por enviar": cruza cada cita próxima (siguientes 14
     * días, sin cancelar) con cada regla activa cuyo "aplica_a" coincida
     * con el estado de la cita, y descarta las combinaciones que YA
     * tienen una fila en "recordatorios" (esas ya se enviaron o fallaron).
     * No es una tabla física -- se recalcula cada vez que se pide.
     */
    public function obtenerPendientes(): array {
        $query = "SELECT
                    c.id AS cita_id, r.id AS regla_id,
                    up.nombre AS paciente_nombre, up.correo AS paciente_correo,
                    s.nombre AS servicio_nombre, c.fecha AS cita_fecha, c.hora AS cita_hora, c.estado AS cita_estado,
                    r.nombre AS regla_nombre, r.timing, r.horas, r.canal, r.mensaje,
                    p.id AS paciente_id,
                    CASE WHEN r.timing = 'antes'
                        THEN TIMESTAMP(c.fecha, c.hora) - INTERVAL r.horas HOUR
                        ELSE TIMESTAMP(c.fecha, c.hora) + INTERVAL r.horas HOUR
                    END AS fecha_programada
                  FROM citas c
                  JOIN pacientes p ON c.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN servicios s ON c.servicio_id = s.id
                  JOIN reglas_recordatorio r ON r.activa = 1
                    AND (r.aplica_a = 'todas' OR (r.aplica_a = 'confirmadas' AND c.estado = 'confirmada') OR (r.aplica_a = 'pendientes' AND c.estado = 'pendiente'))
                  WHERE c.estado != 'cancelada'
                    AND c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
                    AND NOT EXISTS (
                        SELECT 1 FROM recordatorios rec WHERE rec.cita_id = c.id AND rec.regla_id = r.id
                    )
                  ORDER BY fecha_programada ASC";

        return $this->conexion->query($query)->fetchAll();
    }

    public function listarHistorial(?string $busqueda, ?string $canal, ?string $estado): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "(up.nombre LIKE ? OR s.nombre LIKE ?)";
            $like = "%$busqueda%";
            array_push($parametros, $like, $like);
        }
        if ($canal) {
            $condiciones[] = "rec.canal = ?";
            $parametros[] = $canal;
        }
        if ($estado) {
            $condiciones[] = "rec.estado = ?";
            $parametros[] = $estado;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $query = "SELECT
                    rec.id, rec.canal, rec.estado, rec.fecha_envio,
                    up.nombre AS paciente_nombre, s.nombre AS servicio_nombre
                  FROM recordatorios rec
                  JOIN citas c ON rec.cita_id = c.id
                  JOIN servicios s ON c.servicio_id = s.id
                  JOIN pacientes p ON rec.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  $where
                  ORDER BY rec.fecha_envio DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function contarHistorial(): array {
        $filas = $this->conexion->query("SELECT estado, COUNT(*) AS total FROM recordatorios GROUP BY estado")->fetchAll();
        $conteo = ['enviado' => 0, 'fallido' => 0];
        foreach ($filas as $fila) {
            $conteo[$fila['estado']] = (int) $fila['total'];
        }
        return $conteo;
    }

    public function contarEnviadosHoy(): int {
        return (int) $this->conexion->query(
            "SELECT COUNT(*) FROM recordatorios WHERE DATE(fecha_envio) = CURDATE() AND estado = 'enviado'"
        )->fetchColumn();
    }

    public function contarFallidosHoy(): int {
        return (int) $this->conexion->query(
            "SELECT COUNT(*) FROM recordatorios WHERE DATE(fecha_envio) = CURDATE() AND estado = 'fallido'"
        )->fetchColumn();
    }

    /**
     * Los recordatorios ya procesados HOY (enviados o fallidos), para
     * mostrarlos junto a los pendientes en la pestaña Programados y que
     * los filtros de estado tengan algo que mostrar.
     */
    public function obtenerProcesadosHoy(): array {
        $query = "SELECT
                    rec.id, rec.cita_id, rec.regla_id, rec.paciente_id, rec.canal, rec.estado, rec.fecha_envio,
                    up.nombre AS paciente_nombre, up.correo AS paciente_correo,
                    s.nombre AS servicio_nombre, c.fecha AS cita_fecha, c.hora AS cita_hora
                  FROM recordatorios rec
                  JOIN citas c ON rec.cita_id = c.id
                  JOIN servicios s ON c.servicio_id = s.id
                  JOIN pacientes p ON rec.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  WHERE DATE(rec.fecha_envio) = CURDATE()
                  ORDER BY rec.fecha_envio DESC";
        return $this->conexion->query($query)->fetchAll();
    }

    /**
     * Registra el resultado real de un intento de envío -- se llama
     * justo después de que el doctor le da clic a "Enviar" (se abrió
     * su cliente de correo) y confirma, o marca que falló.
     */
    public function registrarEnvio(array $datos): int {
        $query = "INSERT INTO recordatorios (cita_id, regla_id, paciente_id, canal, mensaje_enviado, fecha_programada, estado, fecha_envio)
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['citaId'], $datos['reglaId'], $datos['pacienteId'], $datos['canal'],
            $datos['mensaje'], $datos['fechaProgramada'], $datos['estado'],
        ]);
        return (int) $this->conexion->lastInsertId();
    }
    
    /**
     * Trae un pendiente específico por su combinación cita+regla, para
     * poder enviarlo individualmente sin recalcular toda la lista.
     */
    public function obtenerPendientePorClave(int $citaId, int $reglaId): ?array {
        $query = "SELECT
                    c.id AS cita_id, r.id AS regla_id,
                    up.nombre AS paciente_nombre, up.correo AS paciente_correo,
                    s.nombre AS servicio_nombre, c.fecha AS cita_fecha, c.hora AS cita_hora,
                    r.mensaje, p.id AS paciente_id,
                    CASE WHEN r.timing = 'antes'
                        THEN TIMESTAMP(c.fecha, c.hora) - INTERVAL r.horas HOUR
                        ELSE TIMESTAMP(c.fecha, c.hora) + INTERVAL r.horas HOUR
                    END AS fecha_programada
                  FROM citas c
                  JOIN pacientes p ON c.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN servicios s ON c.servicio_id = s.id
                  JOIN reglas_recordatorio r ON r.id = ?
                  WHERE c.id = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM recordatorios rec WHERE rec.cita_id = c.id AND rec.regla_id = r.id
                    )";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$reglaId, $citaId]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }
}


