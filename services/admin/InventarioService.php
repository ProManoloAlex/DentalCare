<?php

require_once __DIR__ . '/../../repositories/ProductoRepository.php';
require_once __DIR__ . '/../../repositories/MovimientoInventarioRepository.php';
require_once __DIR__ . '/../../repositories/OrdenCompraRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';

class InventarioService {
    private ProductoRepository $productoRepo;
    private MovimientoInventarioRepository $movimientoRepo;
    private OrdenCompraRepository $ordenRepo;

    public function __construct() {
        $this->productoRepo = new ProductoRepository();
        $this->movimientoRepo = new MovimientoInventarioRepository();
        $this->ordenRepo = new OrdenCompraRepository();
    }

    // ============================================================
    // RESUMEN
    // ============================================================

    public function obtenerResumen(): array {
        return [
            'kpis' => [
                'totalProductos' => $this->productoRepo->contarTotalActivos(),
                'stockCritico' => $this->productoRepo->contarStockCritico(),
                'valorInventario' => $this->productoRepo->calcularValorInventario(),
                'porVencer60' => $this->productoRepo->contarPorVencerEnDias(60),
            ],
            'alertasStock' => $this->productoRepo->obtenerAlertasStock(),
            'stockPorCategoria' => $this->productoRepo->obtenerStockPorCategoria(),
            'ultimosMovimientos' => $this->movimientoRepo->obtenerRecientes(6),
        ];
    }

    // ============================================================
    // PRODUCTOS
    // ============================================================

    public function listarProductos(?string $busqueda, ?string $categoria, ?string $stock): array {
        return $this->productoRepo->listar($busqueda, $categoria, $stock);
    }

    public function obtenerProductosParaSelector(): array {
        return $this->productoRepo->obtenerActivosParaSelector();
    }

    public function registrarProducto(array $datos): int {
        $this->validarProducto($datos);
        if ($this->productoRepo->codigoExiste($datos['codigo'])) {
            throw new InvalidArgumentException('Ya existe un producto con ese código.');
        }
        $productoId = $this->productoRepo->crear($datos);
        Auditoria::registrar('inventario', 'Creó un producto', $datos['nombre']);
        return $productoId;
    }

    public function actualizarProducto(int $id, array $datos): void {
        $this->validarProducto($datos);
        if ($this->productoRepo->codigoExiste($datos['codigo'], $id)) {
            throw new InvalidArgumentException('Ya existe otro producto con ese código.');
        }
        if (!$this->productoRepo->obtenerPorId($id)) {
            throw new InvalidArgumentException('El producto no existe.');
        }
        $this->productoRepo->actualizar($id, $datos);
        Auditoria::registrar('inventario', 'Editó un producto', $datos['nombre']);
    }

    private function validarProducto(array $datos): void {
        if (empty($datos['nombre'])) throw new InvalidArgumentException('El nombre es obligatorio.');
        if (empty($datos['codigo'])) throw new InvalidArgumentException('El código es obligatorio.');
        if (empty($datos['categoria'])) throw new InvalidArgumentException('La categoría es obligatoria.');
        if (!isset($datos['stockMinimo']) || $datos['stockMinimo'] < 0) throw new InvalidArgumentException('El stock mínimo no es válido.');
        if (!isset($datos['precio']) || (float) $datos['precio'] < 0) throw new InvalidArgumentException('El precio no es válido.');
    }

    // ============================================================
    // MOVIMIENTOS
    // ============================================================

    public function listarMovimientos(?string $busqueda, ?string $tipo): array {
        return $this->movimientoRepo->listarConDetalle($busqueda, $tipo ? strtolower($tipo) : null);
    }

    public function contarMovimientosPorTipoEsteMes(): array {
        $filas = $this->movimientoRepo->contarPorTipoEsteMes();
        $conteo = ['entrada' => 0, 'salida' => 0, 'ajuste' => 0];
        foreach ($filas as $fila) {
            $conteo[$fila['tipo']] = (int) $fila['total'];
        }
        return $conteo;
    }

    public function registrarMovimiento(array $datos): int {
        if (empty($datos['productoId'])) {
            throw new InvalidArgumentException('Selecciona un producto.');
        }
        if (empty($datos['tipo']) || !in_array($datos['tipo'], ['entrada', 'salida', 'ajuste'], true)) {
            throw new InvalidArgumentException('Tipo de movimiento no válido.');
        }
        if (!isset($datos['cantidad']) || (int) $datos['cantidad'] === 0) {
            throw new InvalidArgumentException('La cantidad no puede ser 0.');
        }
        if ($datos['tipo'] !== 'ajuste' && (int) $datos['cantidad'] < 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }
        if (empty($datos['fecha'])) {
            $datos['fecha'] = date('Y-m-d');
        }
        $movimientoId = $this->movimientoRepo->crear($datos);
        Auditoria::registrar('inventario', 'Registró un movimiento de ' . $datos['tipo'], "Producto #{$datos['productoId']}, cantidad {$datos['cantidad']}");
        return $movimientoId;
    }

    // ============================================================
    // ÓRDENES DE COMPRA
    // ============================================================

    public function listarOrdenes(?string $estado): array {
        return $this->ordenRepo->listar($estado ? strtolower($estado) : null);
    }

    public function obtenerResumenOrdenes(): array {
        return [
            'porEstado' => $this->ordenRepo->contarPorEstado(),
            'proximaAEntregar' => $this->ordenRepo->obtenerProximaAEntregar(),
        ];
    }

    public function registrarOrden(array $datos, array $lineas): int {
        if (empty($datos['proveedor'])) {
            throw new InvalidArgumentException('El proveedor es obligatorio.');
        }
        $lineasLimpias = array_values(array_filter($lineas, fn($l) => !empty($l['productoId'])));
        $ordenId = $this->ordenRepo->crear($datos, $lineasLimpias);
        Auditoria::registrar('inventario', 'Creó una orden de compra', $datos['proveedor']);
        return $ordenId;
    }

    public function cambiarEstadoOrden(int $ordenId, string $nuevoEstado, ?int $doctorId): void {
        if (!in_array($nuevoEstado, ['pendiente', 'aprobada', 'recibida', 'cancelada'], true)) {
            throw new InvalidArgumentException('Estado no válido.');
        }
        $this->ordenRepo->cambiarEstado($ordenId, $nuevoEstado, $doctorId);
        Auditoria::registrar('inventario', 'Cambió el estado de una orden de compra a ' . $nuevoEstado, "Orden #$ordenId");
    }
}