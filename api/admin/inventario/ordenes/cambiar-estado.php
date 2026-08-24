<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
$doctorId = obtenerDoctorIdDeSesion();
require_once __DIR__ . '/../../../../services/admin/InventarioService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$ordenId = (int) ($datos['ordenId'] ?? 0);
$nuevoEstado = $datos['estado'] ?? '';

if ($ordenId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el ordenId.']);
    exit;
}

try {
    $service = new InventarioService();
    $service->cambiarEstadoOrden($ordenId, $nuevoEstado, $doctorId);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar la orden: ' . $e->getMessage()]);
}