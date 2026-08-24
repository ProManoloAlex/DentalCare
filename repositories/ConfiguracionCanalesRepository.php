<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class ConfiguracionCanalesRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function obtener(): array {
        $fila = $this->conexion->query("SELECT * FROM configuracion_canales WHERE id = 1")->fetch();
        return $fila ?: [];
    }

    public function guardar(array $datos): void {
        $query = "UPDATE configuracion_canales SET
                    email_activo = ?, email_remitente = ?, email_nombre_remitente = ?, email_asunto = ?,
                    whatsapp_activo = ?, whatsapp_numero = ?, whatsapp_remitente = ?
                  WHERE id = 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            !empty($datos['emailActivo']) ? 1 : 0, $datos['emailRemitente'] ?: null,
            $datos['emailNombreRemitente'] ?: null, $datos['emailAsunto'] ?: null,
            !empty($datos['whatsappActivo']) ? 1 : 0, $datos['whatsappNumero'] ?? null, $datos['whatsappRemitente'] ?? null,
        ]);
    }
}