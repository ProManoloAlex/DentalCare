<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class DoctorRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listar(): array {
        $query = "SELECT d.id, d.especialidad, d.consultorio, u.id AS usuario_id, u.nombre, u.correo, u.activo, u.ultimo_login
                  FROM doctores d
                  JOIN usuarios u ON d.usuario_id = u.id
                  ORDER BY u.nombre ASC";
        return $this->conexion->query($query)->fetchAll();
    }

    public function obtenerPorId(int $id): ?array {
        $query = "SELECT d.id, d.especialidad, d.consultorio, u.id AS usuario_id, u.nombre, u.correo, u.activo
                  FROM doctores d JOIN usuarios u ON d.usuario_id = u.id WHERE d.id = ?";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    public function correoExiste(string $correo, ?int $usuarioIdExcluir = null): bool {
        $query = "SELECT COUNT(*) FROM usuarios WHERE correo = ?";
        $parametros = [$correo];
        if ($usuarioIdExcluir) {
            $query .= " AND id != ?";
            $parametros[] = $usuarioIdExcluir;
        }
        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos): int {
        $this->conexion->beginTransaction();
        try {
            $hash = password_hash($datos['contrasena'], PASSWORD_BCRYPT);

            $stmtUsuario = $this->conexion->prepare(
                "INSERT INTO usuarios (nombre, correo, contrasenna, rol, activo) VALUES (?, ?, ?, 'doctor', 1)"
            );
            $stmtUsuario->execute([$datos['nombre'], $datos['correo'], $hash]);
            $usuarioId = (int) $this->conexion->lastInsertId();

            $stmtDoctor = $this->conexion->prepare(
                "INSERT INTO doctores (usuario_id, especialidad, consultorio) VALUES (?, ?, ?)"
            );
            $stmtDoctor->execute([$usuarioId, $datos['especialidad'] ?: null, $datos['consultorio'] ?: null]);
            $doctorId = (int) $this->conexion->lastInsertId();

            $this->conexion->commit();
            return $doctorId;
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $doctorId, array $datos): void {
        $this->conexion->beginTransaction();
        try {
            $stmtUsuarioId = $this->conexion->prepare("SELECT usuario_id FROM doctores WHERE id = ?");
            $stmtUsuarioId->execute([$doctorId]);
            $usuarioId = $stmtUsuarioId->fetchColumn();

            if (!$usuarioId) {
                throw new InvalidArgumentException('Doctor no encontrado.');
            }

            $stmtUsuario = $this->conexion->prepare("UPDATE usuarios SET nombre = ?, correo = ? WHERE id = ?");
            $stmtUsuario->execute([$datos['nombre'], $datos['correo'], $usuarioId]);

            $stmtDoctor = $this->conexion->prepare("UPDATE doctores SET especialidad = ?, consultorio = ? WHERE id = ?");
            $stmtDoctor->execute([$datos['especialidad'] ?: null, $datos['consultorio'] ?: null, $doctorId]);

            $this->conexion->commit();
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado(int $doctorId, bool $activo): void {
        $stmt = $this->conexion->prepare(
            "UPDATE usuarios u JOIN doctores d ON d.usuario_id = u.id SET u.activo = ? WHERE d.id = ?"
        );
        $stmt->execute([$activo ? 1 : 0, $doctorId]);
    }
}
