<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/FinanzasService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$gastoId = (int) ($datos['gastoId'] ?? 0);

if ($gastoId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el gastoId.']);
    exit;
}

try {
    $service = new FinanzasService();
    $service->marcarGastoPagado($gastoId);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar el gasto: ' . $e->getMessage()]);
}