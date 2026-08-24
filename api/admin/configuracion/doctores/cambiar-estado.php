<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/ConfiguracionService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$doctorId = (int) ($datos['doctorId'] ?? 0);
$activo = !empty($datos['activo']);

if ($doctorId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el doctorId.']);
    exit;
}

try {
    $service = new ConfiguracionService();
    $service->cambiarEstadoDoctor($doctorId, $activo);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar el doctor: ' . $e->getMessage()]);
}
