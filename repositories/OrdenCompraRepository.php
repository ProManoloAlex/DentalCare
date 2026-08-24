<?php

require_once __DIR__ . '/../config/Conexion_DB.php';
require_once __DIR__ . '/MovimientoInventarioRepository.php';

class OrdenCompraRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listar(?string $estado): array {
        $query = "SELECT o.*, (SELECT COUNT(*) FROM ordenes_compra_lineas WHERE orden_id = o.id) AS productos_count
                  FROM ordenes_compra o";
        $parametros = [];
        if ($estado) {
            $query .= " WHERE estado = ?";
            $parametros[] = $estado;
        }
        $query .= " ORDER BY o.fecha_creacion DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function contarPorEstado(): array {
        $filas = $this->conexion->query("SELECT estado, COUNT(*) AS total FROM ordenes_compra GROUP BY estado")->fetchAll();
        $conteo = ['pendiente' => 0, 'aprobada' => 0, 'recibida' => 0, 'cancelada' => 0];
        foreach ($filas as $fila) {
            $conteo[$fila['estado']] = (int) $fila['total'];
        }
        return $conteo;
    }

    public function obtenerProximaAEntregar(): ?array {
        $stmt = $this->conexion->query(
            "SELECT folio, proveedor, fecha_entrega_estimada FROM ordenes_compra
             WHERE estado IN ('pendiente','aprobada') AND fecha_entrega_estimada IS NOT NULL
             ORDER BY fecha_entrega_estimada ASC LIMIT 1"
        );
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    private function generarFolio(): string {
        $anio = date('Y');
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM ordenes_compra WHERE folio LIKE ?"
        );
        $stmt->execute(["OC-$anio-%"]);
        $consecutivo = (int) $stmt->fetchColumn() + 1;
        return sprintf('OC-%s-%03d', $anio, $consecutivo);
    }

    /**
     * @param array $lineas cada elemento: ['productoId' => int, 'cantidad' => int, 'precioUnitario' => float]
     */
    public function crear(array $datos, array $lineas): int {
        if (empty($lineas)) {
            throw new InvalidArgumentException('La orden necesita al menos un producto.');
        }

        $this->conexion->beginTransaction();
        try {
            $total = array_reduce($lineas, fn($acc, $l) => $acc + ($l['cantidad'] * $l['precioUnitario']), 0);

            $stmtOrden = $this->conexion->prepare(
                "INSERT INTO ordenes_compra (folio, proveedor, fecha_entrega_estimada, estado, total, notas)
                 VALUES (?, ?, ?, 'pendiente', ?, ?)"
            );
            $folio = $this->generarFolio();
            $stmtOrden->execute([$folio, $datos['proveedor'], $datos['fechaEntrega'] ?: null, $total, $datos['notas'] ?: null]);
            $ordenId = (int) $this->conexion->lastInsertId();

            $stmtLinea = $this->conexion->prepare(
                "INSERT INTO ordenes_compra_lineas (orden_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)"
            );
            foreach ($lineas as $linea) {
                $stmtLinea->execute([$ordenId, $linea['productoId'], $linea['cantidad'], $linea['precioUnitario']]);
            }

            $this->conexion->commit();
            return $ordenId;
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    /**
     * Cambia el estado de la orden. Si el nuevo estado es "recibida",
     * genera automáticamente un movimiento de "entrada" por cada línea
     * y suma el stock -- así no hay que registrar el mismo recibo dos
     * veces (una en la orden, otra a mano en Movimientos).
     */
    public function cambiarEstado(int $ordenId, string $nuevoEstado, ?int $doctorId): void {
        $this->conexion->beginTransaction();
        try {
            $stmtEstadoActual = $this->conexion->prepare("SELECT estado FROM ordenes_compra WHERE id = ? FOR UPDATE");
            $stmtEstadoActual->execute([$ordenId]);
            $estadoActual = $stmtEstadoActual->fetchColumn();

            if ($estadoActual === false) {
                throw new InvalidArgumentException('La orden no existe.');
            }
            if ($estadoActual === 'recibida') {
                throw new InvalidArgumentException('Esta orden ya fue marcada como recibida.');
            }

            $stmtUpdate = $this->conexion->prepare("UPDATE ordenes_compra SET estado = ? WHERE id = ?");
            $stmtUpdate->execute([$nuevoEstado, $ordenId]);

            if ($nuevoEstado === 'recibida') {
                $this->generarMovimientosDeRecepcion($ordenId, $doctorId);
            }

            $this->conexion->commit();
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    private function generarMovimientosDeRecepcion(int $ordenId, ?int $doctorId): void {
        $stmtLineas = $this->conexion->prepare(
            "SELECT producto_id, cantidad, precio_unitario FROM ordenes_compra_lineas WHERE orden_id = ?"
        );
        $stmtLineas->execute([$ordenId]);
        $lineas = $stmtLineas->fetchAll();

        $stmtFolio = $this->conexion->prepare("SELECT folio FROM ordenes_compra WHERE id = ?");
        $stmtFolio->execute([$ordenId]);
        $folio = $stmtFolio->fetchColumn();

        foreach ($lineas as $linea) {
            $stmtStock = $this->conexion->prepare("SELECT stock FROM productos WHERE id = ? FOR UPDATE");
            $stmtStock->execute([$linea['producto_id']]);
            $stockActual = (int) $stmtStock->fetchColumn();
            $nuevoStock = $stockActual + (int) $linea['cantidad'];

            $stmtMov = $this->conexion->prepare(
                "INSERT INTO movimientos_inventario
                    (producto_id, doctor_id, tipo, cantidad, stock_antes, stock_despues, motivo, monto, fecha)
                 VALUES (?, ?, 'entrada', ?, ?, ?, ?, ?, CURDATE())"
            );
            $stmtMov->execute([
                $linea['producto_id'], $doctorId, $linea['cantidad'], $stockActual, $nuevoStock,
                "Recepción de orden $folio", $linea['cantidad'] * $linea['precio_unitario'],
            ]);

            $stmtStockUpdate = $this->conexion->prepare("UPDATE productos SET stock = ? WHERE id = ?");
            $stmtStockUpdate->execute([$nuevoStock, $linea['producto_id']]);
        }
    }
}
