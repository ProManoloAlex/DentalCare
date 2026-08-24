<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class ProductoRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listar(?string $busqueda, ?string $categoria, ?string $stock): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "(nombre LIKE ? OR codigo LIKE ? OR marca LIKE ?)";
            $like = "%$busqueda%";
            array_push($parametros, $like, $like, $like);
        }
        if ($categoria) {
            $condiciones[] = "categoria = ?";
            $parametros[] = $categoria;
        }
        if ($stock === 'critico') {
            $condiciones[] = "stock <= stock_minimo";
        } elseif ($stock === 'ok') {
            $condiciones[] = "stock > stock_minimo";
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $query = "SELECT * FROM productos $where ORDER BY activo DESC, nombre ASC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array {
        $stmt = $this->conexion->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    public function obtenerActivosParaSelector(): array {
        $stmt = $this->conexion->query("SELECT id, nombre, precio, stock, unidad FROM productos WHERE activo = 1 ORDER BY nombre ASC");
        return $stmt->fetchAll();
    }

    public function crear(array $datos): int {
        $query = "INSERT INTO productos
                    (nombre, codigo, categoria, marca, unidad, stock, stock_minimo, stock_maximo, precio, vencimiento, proveedor, ubicacion, activo)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['nombre'], $datos['codigo'], $datos['categoria'], $datos['marca'] ?: null, $datos['unidad'] ?: null,
            $datos['stock'], $datos['stockMinimo'], $datos['stockMaximo'] ?: null, $datos['precio'],
            $datos['vencimiento'] ?: null, $datos['proveedor'] ?: null, $datos['ubicacion'] ?: null,
            !empty($datos['activo']) ? 1 : 0,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void {
        $query = "UPDATE productos SET
                    nombre = ?, codigo = ?, categoria = ?, marca = ?, unidad = ?,
                    stock_minimo = ?, stock_maximo = ?, precio = ?, vencimiento = ?,
                    proveedor = ?, ubicacion = ?, activo = ?
                  WHERE id = ?";
        // Nota: "stock" (stock actual) NO se edita aquí a mano -- solo cambia
        // vía movimientos, para que siempre quede el historial de por qué cambió.
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['nombre'], $datos['codigo'], $datos['categoria'], $datos['marca'] ?: null, $datos['unidad'] ?: null,
            $datos['stockMinimo'], $datos['stockMaximo'] ?: null, $datos['precio'],
            $datos['vencimiento'] ?: null, $datos['proveedor'] ?: null, $datos['ubicacion'] ?: null,
            !empty($datos['activo']) ? 1 : 0, $id,
        ]);
    }

    public function actualizarStock(int $id, int $nuevoStock): void {
        $stmt = $this->conexion->prepare("UPDATE productos SET stock = ? WHERE id = ?");
        $stmt->execute([$nuevoStock, $id]);
    }

    public function codigoExiste(string $codigo, ?int $excluirId = null): bool {
        $query = "SELECT COUNT(*) FROM productos WHERE codigo = ?";
        $parametros = [$codigo];
        if ($excluirId) {
            $query .= " AND id != ?";
            $parametros[] = $excluirId;
        }
        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchColumn() > 0;
    }

    // ---------- Resumen (pestaña Resumen) ----------

    public function contarTotalActivos(): int {
        return (int) $this->conexion->query("SELECT COUNT(*) FROM productos WHERE activo = 1")->fetchColumn();
    }

    public function contarStockCritico(): int {
        return (int) $this->conexion->query("SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock <= stock_minimo")->fetchColumn();
    }

    public function calcularValorInventario(): float {
        return (float) $this->conexion->query("SELECT COALESCE(SUM(stock * precio), 0) FROM productos WHERE activo = 1")->fetchColumn();
    }

    public function contarPorVencerEnDias(int $dias): int {
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM productos
             WHERE activo = 1 AND vencimiento IS NOT NULL
               AND vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)"
        );
        $stmt->execute([$dias]);
        return (int) $stmt->fetchColumn();
    }

    public function obtenerAlertasStock(): array {
        $stmt = $this->conexion->query(
            "SELECT id, nombre, stock, stock_minimo, unidad FROM productos
             WHERE activo = 1 AND stock <= stock_minimo
             ORDER BY (stock / GREATEST(stock_minimo, 1)) ASC"
        );
        return $stmt->fetchAll();
    }

    public function obtenerStockPorCategoria(): array {
        $stmt = $this->conexion->query(
            "SELECT categoria, COUNT(*) AS total_productos, COALESCE(SUM(stock * precio), 0) AS valor
             FROM productos WHERE activo = 1 GROUP BY categoria"
        );
        return $stmt->fetchAll();
    }
}