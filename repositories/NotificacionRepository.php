<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class NotificacionRepository {
    private PDO $conexion;

    // Debe coincidir EXACTO con la columna "evento" de notificaciones_preferencias
    private const EVENTO_24H = 'Recordatorio de cita (24h antes)';
    private const EVENTO_2H = 'Recordatorio de cita (2h antes)';
    private const EVENTO_SEGUIMIENTO = 'Seguimiento post-tratamiento';

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listarRecientes(int $limite): array {
        $stmt = $this->conexion->prepare("SELECT * FROM notificaciones ORDER BY fecha_creacion DESC LIMIT " . (int) $limite);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function contarNoLeidas(): int {
        return (int) $this->conexion->query("SELECT COUNT(*) FROM notificaciones WHERE leida = 0")->fetchColumn();
    }

    public function marcarLeida(int $id): void {
        $stmt = $this->conexion->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function marcarTodasLeidas(): void {
        $this->conexion->exec("UPDATE notificaciones SET leida = 1 WHERE leida = 0");
    }

    /**
     * Revisa los 3 eventos "por tiempo" (no los dispara un Service, los
     * detecta esta consulta) y crea la notificación si hace falta. Se
     * llama cada vez que se abre la campanita -- no hay cron; el propio
     * tráfico normal del sistema actúa como disparador, igual que ya
     * hace Recordatorios con sus "programados".
     *
     * Cada INSERT respeta la preferencia "app" de ese evento y NUNCA
     * duplica: solo entra si no existe ya una notificación de ese tipo
     * para esa misma cita.
     */
    public function revisarEventosDeTiempo(): void {
        $this->revisar24h();
        $this->revisar2h();
        $this->revisarSeguimiento();
    }

    private function revisar24h(): void {
        $query = "INSERT INTO notificaciones (tipo, titulo, mensaje, paciente_id, cita_id)
                  SELECT ?, 'Cita en 24 horas', CONCAT(up.nombre, ' — ', s.nombre, ' mañana a las ', TIME_FORMAT(c.hora, '%H:%i')), c.paciente_id, c.id
                  FROM citas c
                  JOIN pacientes p ON c.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.estado != 'cancelada'
                    AND TIMESTAMP(c.fecha, c.hora) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                    AND EXISTS (SELECT 1 FROM notificaciones_preferencias np WHERE np.evento = ? AND np.app = 1)
                    AND NOT EXISTS (SELECT 1 FROM notificaciones n WHERE n.cita_id = c.id AND n.tipo = ?)";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([self::EVENTO_24H, self::EVENTO_24H, self::EVENTO_24H]);
    }

    private function revisar2h(): void {
        $query = "INSERT INTO notificaciones (tipo, titulo, mensaje, paciente_id, cita_id)
                  SELECT ?, 'Cita en 2 horas', CONCAT(up.nombre, ' — ', s.nombre, ' hoy a las ', TIME_FORMAT(c.hora, '%H:%i')), c.paciente_id, c.id
                  FROM citas c
                  JOIN pacientes p ON c.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.estado != 'cancelada'
                    AND TIMESTAMP(c.fecha, c.hora) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR)
                    AND EXISTS (SELECT 1 FROM notificaciones_preferencias np WHERE np.evento = ? AND np.app = 1)
                    AND NOT EXISTS (SELECT 1 FROM notificaciones n WHERE n.cita_id = c.id AND n.tipo = ?)";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([self::EVENTO_2H, self::EVENTO_2H, self::EVENTO_2H]);
    }

    /**
     * Regla elegida (documentada porque es una decisión de negocio, no
     * un dato que ya existiera): "seguimiento" se dispara 2 días después
     * de una cita completada. Si quieres otro número de días, es la
     * única línea que hay que tocar (el INTERVAL de abajo).
     */
    private function revisarSeguimiento(): void {
        $query = "INSERT INTO notificaciones (tipo, titulo, mensaje, paciente_id, cita_id)
                  SELECT ?, 'Seguimiento pendiente', CONCAT('Dar seguimiento a ', up.nombre, ' tras su cita de ', s.nombre), c.paciente_id, c.id
                  FROM citas c
                  JOIN pacientes p ON c.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.estado = 'completada'
                    AND c.fecha = DATE_SUB(CURDATE(), INTERVAL 2 DAY)
                    AND EXISTS (SELECT 1 FROM notificaciones_preferencias np WHERE np.evento = ? AND np.app = 1)
                    AND NOT EXISTS (SELECT 1 FROM notificaciones n WHERE n.cita_id = c.id AND n.tipo = ?)";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([self::EVENTO_SEGUIMIENTO, self::EVENTO_SEGUIMIENTO, self::EVENTO_SEGUIMIENTO]);
    }
}
