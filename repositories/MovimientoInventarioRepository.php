<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class MovimientoInventarioRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listarConDetalle(?string $busqueda, ?string $tipo): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "(p.nombre LIKE ? OR m.motivo LIKE ?)";
            $like = "%$busqueda%";
            array_push($parametros, $like, $like);
        }
        if ($tipo) {
            $condiciones[] = "m.tipo = ?";
            $parametros[] = $tipo;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $query = "SELECT
                    m.id, m.tipo, m.cantidad, m.stock_antes, m.stock_despues, m.motivo, m.monto, m.fecha,
                    p.nombre AS producto_nombre,
                    COALESCE(u.nombre, 'Sistema') AS registrado_por
                  FROM movimientos_inventario m
                  JOIN productos p ON m.producto_id = p.id
                  LEFT JOIN doctores d ON m.doctor_id = d.id
                  LEFT JOIN usuarios u ON d.usuario_id = u.id
                  $where
                  ORDER BY m.fecha_creacion DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtenerRecientes(int $limite): array {
        $query = "SELECT m.tipo, m.cantidad, m.fecha, p.nombre AS producto_nombre
                  FROM movimientos_inventario m
                  JOIN productos p ON m.producto_id = p.id
                  ORDER BY m.fecha_creacion DESC LIMIT ?";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function contarPorTipoEsteMes(): array {
        $query = "SELECT tipo, COUNT(*) AS total FROM movimientos_inventario
                   WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
                   GROUP BY tipo";
        return $this->conexion->query($query)->fetchAll();
    }

    /**
     * Registra el movimiento Y actualiza el stock del producto en la
     * misma transacción -- nunca deben quedar desincronizados.
     */
    public function crear(array $datos): int {
        $this->conexion->beginTransaction();
        try {
            $stmtProducto = $this->conexion->prepare("SELECT stock FROM productos WHERE id = ? FOR UPDATE");
            $stmtProducto->execute([$datos['productoId']]);
            $stockActual = $stmtProducto->fetchColumn();

            if ($stockActual === false) {
                throw new InvalidArgumentException('El producto no existe.');
            }

            if ($datos['tipo'] === 'salida' && abs((int) $datos['cantidad']) > (int) $stockActual) {
                throw new InvalidArgumentException("No hay suficiente stock: solo tienes $stockActual disponible(s).");
            }

            $delta = match ($datos['tipo']) {
                'salida' => -abs((int) $datos['cantidad']),
                'ajuste' => (int) $datos['cantidad'], // ya viene firmado desde el JS (puede ser negativo)
                default => abs((int) $datos['cantidad']), // entrada
            };
            $nuevoStock = max(0, (int) $stockActual + $delta);

            $stmtMovimiento = $this->conexion->prepare(
                "INSERT INTO movimientos_inventario
                    (producto_id, doctor_id, tipo, cantidad, stock_antes, stock_despues, motivo, monto, fecha)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmtMovimiento->execute([
                $datos['productoId'], $datos['doctorId'], $datos['tipo'], abs($datos['cantidad']),
                (int) $stockActual, $nuevoStock, $datos['motivo'] ?: null, $datos['monto'] ?? null,
                $datos['fecha'],
            ]);
            $movimientoId = (int) $this->conexion->lastInsertId();

            $stmtActualizar = $this->conexion->prepare("UPDATE productos SET stock = ? WHERE id = ?");
            $stmtActualizar->execute([$nuevoStock, $datos['productoId']]);

            $this->conexion->commit();
            return $movimientoId;
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }
}