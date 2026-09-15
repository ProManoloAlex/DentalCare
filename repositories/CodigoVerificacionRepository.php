<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class CodigoVerificacionRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function limpiarCodigosPrevios(int $usuarioId): void {
        $stmt = $this->conexion->prepare("DELETE FROM codigos_verificacion WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
    }

    public function crearCodigo(int $usuarioId, string $codigo, string $expiraEn): void {
        $stmt = $this->conexion->prepare(
            "INSERT INTO codigos_verificacion (usuario_id, codigo, expira_en) VALUES (?, ?, ?)"
        );
        $stmt->execute([$usuarioId, $codigo, $expiraEn]);
    }

    public function obtenerVigentePorUsuario(int $usuarioId, string $codigo): ?array {
        $stmt = $this->conexion->prepare(
            "SELECT id, expira_en, usado FROM codigos_verificacion
             WHERE usuario_id = ? AND codigo = ?
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$usuarioId, $codigo]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function marcarUsado(int $id): void {
        $stmt = $this->conexion->prepare("UPDATE codigos_verificacion SET usado = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function marcarCorreoVerificado(int $usuarioId): void {
        $stmt = $this->conexion->prepare("UPDATE usuarios SET correo_verificado = 1 WHERE id = ?");
        $stmt->execute([$usuarioId]);
    }

    public function obtenerUsuarioPorCorreo(string $correo): ?array {
        $stmt = $this->conexion->prepare(
            "SELECT id, nombre, correo, correo_verificado FROM usuarios WHERE correo = ?"
        );
        $stmt->execute([$correo]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}