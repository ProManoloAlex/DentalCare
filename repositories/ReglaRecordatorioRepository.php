<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class ReglaRecordatorioRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listar(): array {
        return $this->conexion->query("SELECT * FROM reglas_recordatorio ORDER BY fecha_creacion DESC")->fetchAll();
    }

    public function listarActivas(): array {
        return $this->conexion->query("SELECT * FROM reglas_recordatorio WHERE activa = 1")->fetchAll();
    }

    public function obtenerPorId(int $id): ?array {
        $stmt = $this->conexion->prepare("SELECT * FROM reglas_recordatorio WHERE id = ?");
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    public function contarActivas(): int {
        return (int) $this->conexion->query("SELECT COUNT(*) FROM reglas_recordatorio WHERE activa = 1")->fetchColumn();
    }

    public function crear(array $datos): int {
        $query = "INSERT INTO reglas_recordatorio (nombre, descripcion, timing, horas, canal, aplica_a, mensaje, activa)
                  VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['nombre'], $datos['descripcion'] ?: null, $datos['timing'], $datos['horas'],
            $datos['canal'], $datos['aplicaA'], $datos['mensaje'],
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void {
        $query = "UPDATE reglas_recordatorio SET nombre = ?, descripcion = ?, timing = ?, horas = ?, canal = ?, aplica_a = ?, mensaje = ? WHERE id = ?";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['nombre'], $datos['descripcion'] ?: null, $datos['timing'], $datos['horas'],
            $datos['canal'], $datos['aplicaA'], $datos['mensaje'], $id,
        ]);
    }

    public function cambiarEstado(int $id, bool $activa): void {
        $stmt = $this->conexion->prepare("UPDATE reglas_recordatorio SET activa = ? WHERE id = ?");
        $stmt->execute([$activa ? 1 : 0, $id]);
    }
}
