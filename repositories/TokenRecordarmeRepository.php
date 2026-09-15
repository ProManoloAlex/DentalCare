<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class TokenRecordarmeRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function crearToken(int $usuarioId, string $tokenHash, string $expiraEn): void {
        $stmt = $this->conexion->prepare(
            "INSERT INTO tokens_recordarme (usuario_id, token_hash, expira_en) VALUES (?, ?, ?)"
        );
        $stmt->execute([$usuarioId, $tokenHash, $expiraEn]);
    }

    public function obtenerValido(int $usuarioId, string $tokenHash): ?array {
        $stmt = $this->conexion->prepare(
            "SELECT id FROM tokens_recordarme
             WHERE usuario_id = ? AND token_hash = ? AND expira_en > NOW()"
        );
        $stmt->execute([$usuarioId, $tokenHash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function eliminarPorUsuario(int $usuarioId): void {
        $stmt = $this->conexion->prepare("DELETE FROM tokens_recordarme WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
    }
}