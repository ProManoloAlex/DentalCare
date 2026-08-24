<?php
require_once __DIR__ . '/../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../services/admin/RecetaService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$recetaId = (int) ($datos['recetaId'] ?? 0);
$medicamentos = $datos['medicamentos'] ?? [];

if ($recetaId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el recetaId.']);
    exit;
}

try {
    $service = new RecetaService();
    $service->actualizarReceta($recetaId, $datos, $medicamentos);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar la receta: ' . $e->getMessage()]);
}