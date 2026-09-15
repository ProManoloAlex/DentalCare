<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class RegistroRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function correoExiste(string $correo): bool {
        $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = ?");
        $stmt->execute([$correo]);
        return $stmt->fetchColumn() > 0;
    }

    public function crearUsuarioPaciente(string $nombre, string $correo, string $contrasennaHash): int {
        $this->conexion->beginTransaction();
        try {
            $stmt = $this->conexion->prepare(
                "INSERT INTO usuarios (nombre, correo, contrasenna, rol, correo_verificado) VALUES (?, ?, ?, 'paciente', 0)"
            );
            $stmt->execute([$nombre, $correo, $contrasennaHash]);
            $usuarioId = (int) $this->conexion->lastInsertId();

            // Igual que antes: toda cuenta 'paciente' necesita su fila en pacientes
            $stmtPaciente = $this->conexion->prepare("INSERT INTO pacientes (usuario_id) VALUES (?)");
            $stmtPaciente->execute([$usuarioId]);

            $this->conexion->commit();
            return $usuarioId;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }
}