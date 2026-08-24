<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/InventarioService.php';

header('Content-Type: application/json');

$estado = $_GET['estado'] ?? null;

try {
    $service = new InventarioService();
    echo json_encode([
        'ok' => true,
        'ordenes' => $service->listarOrdenes($estado),
        'resumen' => $service->obtenerResumenOrdenes(),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al listar órdenes: ' . $e->getMessage()]);
}
