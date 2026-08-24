<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
$doctorId = obtenerDoctorIdDeSesion();
require_once __DIR__ . '/../../../../services/admin/InventarioService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$datos['doctorId'] = $doctorId;

try {
    $service = new InventarioService();
    $movimientoId = $service->registrarMovimiento($datos);
    echo json_encode(['ok' => true, 'movimientoId' => $movimientoId]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al registrar el movimiento: ' . $e->getMessage()]);
}
