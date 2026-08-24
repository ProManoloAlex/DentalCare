<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/InventarioService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$lineas = $datos['lineas'] ?? [];

try {
    $service = new InventarioService();
    $ordenId = $service->registrarOrden($datos, $lineas);
    echo json_encode(['ok' => true, 'ordenId' => $ordenId]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al crear la orden: ' . $e->getMessage()]);
}
