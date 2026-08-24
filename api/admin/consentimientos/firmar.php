<?php
require_once __DIR__ . '/../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../services/admin/ConsentimientoService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int) ($datos['consentimientoId'] ?? 0);
$estado = $datos['estado'] ?? '';
$firma = $datos['firma'] ?? null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el consentimientoId.']);
    exit;
}

try {
    $service = new ConsentimientoService();
    $service->firmar($id, $estado, $firma, 'doctor');
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar el consentimiento: ' . $e->getMessage()]);
}
