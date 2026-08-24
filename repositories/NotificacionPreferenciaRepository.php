<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class NotificacionPreferenciaRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listarEventos(): array {
        return $this->conexion->query("SELECT * FROM notificaciones_preferencias ORDER BY id ASC")->fetchAll();
    }

    public function listarAlertasInternas(): array {
        return $this->conexion->query("SELECT * FROM alertas_internas_preferencias ORDER BY id ASC")->fetchAll();
    }

    /** @param array $eventos [['id' => int, 'email' => bool, 'app' => bool], ...] */
    public function guardarEventos(array $eventos): void {
        $stmt = $this->conexion->prepare("UPDATE notificaciones_preferencias SET email = ?, app = ? WHERE id = ?");
        foreach ($eventos as $e) {
            $stmt->execute([!empty($e['email']) ? 1 : 0, !empty($e['app']) ? 1 : 0, $e['id']]);
        }
    }

    /** @param array $alertas [['id' => int, 'activo' => bool], ...] */
    public function guardarAlertasInternas(array $alertas): void {
        $stmt = $this->conexion->prepare("UPDATE alertas_internas_preferencias SET activo = ? WHERE id = ?");
        foreach ($alertas as $a) {
            $stmt->execute([!empty($a['activo']) ? 1 : 0, $a['id']]);
        }
    }
}
