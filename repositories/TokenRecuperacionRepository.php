<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class TokenRecuperacionRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function obtenerUsuarioPacientePorCorreo(string $correo): ?array {
        $stmt = $this->conexion->prepare(
            "SELECT id, nombre, correo FROM usuarios WHERE correo = ? AND rol = 'paciente' AND activo = 1"
        );
        $stmt->execute([$correo]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function limpiarTokensPrevios(int $usuarioId): void {
        $stmt = $this->conexion->prepare("DELETE FROM tokens_recuperacion WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
    }

    public function crearToken(int $usuarioId, string $token, string $expiraEn): void {
        $stmt = $this->conexion->prepare(
            "INSERT INTO tokens_recuperacion (usuario_id, token, expira_en) VALUES (?, ?, ?)"
        );
        $stmt->execute([$usuarioId, $token, $expiraEn]);
    }

    public function obtenerPorToken(string $token): ?array {
        $stmt = $this->conexion->prepare(
            "SELECT id, usuario_id, expira_en, usado FROM tokens_recuperacion WHERE token = ?"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function marcarUsado(string $token): void {
        $stmt = $this->conexion->prepare("UPDATE tokens_recuperacion SET usado = 1 WHERE token = ?");
        $stmt->execute([$token]);
    }
}