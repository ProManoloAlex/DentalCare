<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/InventarioService.php';

header('Content-Type: application/json');

$busqueda = $_GET['buscar'] ?? null;
$categoria = $_GET['categoria'] ?? null;
$stock = $_GET['stock'] ?? null;

try {
    $service = new InventarioService();
    echo json_encode(['ok' => true, 'productos' => $service->listarProductos($busqueda, $categoria, $stock)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al listar productos: ' . $e->getMessage()]);
}
