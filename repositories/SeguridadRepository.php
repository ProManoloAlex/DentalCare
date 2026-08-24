<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class SeguridadRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function obtenerHashActual(int $usuarioId): ?string {
        $stmt = $this->conexion->prepare("SELECT contrasenna FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        $hash = $stmt->fetchColumn();
        return $hash ?: null;
    }

    public function actualizarPassword(int $usuarioId, string $nuevaPassword): void {
        $hash = password_hash($nuevaPassword, PASSWORD_BCRYPT);
        $stmt = $this->conexion->prepare("UPDATE usuarios SET contrasenna = ? WHERE id = ?");
        $stmt->execute([$hash, $usuarioId]);
    }
}
