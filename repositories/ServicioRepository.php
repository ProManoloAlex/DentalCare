<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class ServicioRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listar(?string $busqueda, ?string $categoria, string $orden): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "nombre LIKE ?";
            $parametros[] = "%$busqueda%";
        }
        if ($categoria) {
            $condiciones[] = "categoria = ?";
            $parametros[] = $categoria;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
        // Los pausados (activo=0) se muestran igual, solo se ordenan al final
        $ordenSql = $orden === 'precio' ? 'precio ASC' : 'activo DESC, nombre ASC';

        $query = "SELECT id, nombre, categoria, descripcion, duracion_min, precio, activo 
                  FROM servicios $where ORDER BY $ordenSql";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array {
        $stmt = $this->conexion->prepare("SELECT * FROM servicios WHERE id = ?");
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    public function crear(array $datos): int {
        $query = "INSERT INTO servicios (nombre, categoria, descripcion, duracion_min, precio) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['nombre'], $datos['categoria'], $datos['descripcion'],
            $datos['duracionMin'], $datos['precio'],
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void {
        $query = "UPDATE servicios SET nombre = ?, categoria = ?, descripcion = ?, duracion_min = ?, precio = ? WHERE id = ?";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['nombre'], $datos['categoria'], $datos['descripcion'],
            $datos['duracionMin'], $datos['precio'], $id,
        ]);
    }

    public function cambiarEstado(int $id, bool $activo): void {
        $stmt = $this->conexion->prepare("UPDATE servicios SET activo = ? WHERE id = ?");
        $stmt->execute([$activo ? 1 : 0, $id]);
    }

    /**
     * Intenta borrar de verdad. Si el servicio ya se usó en alguna
     * cita, la llave foránea de "citas.servicio_id" lo va a impedir
     * (MySQL lanza un error de restricción) — lo capturamos aquí y
     * lo convertimos en un mensaje claro en vez de un error crudo.
     */
    public function eliminar(int $id): bool {
        try {
            $stmt = $this->conexion->prepare("DELETE FROM servicios WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        } catch (PDOException $e) {
            // Código 23000 = violación de restricción de integridad (FK)
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }
}