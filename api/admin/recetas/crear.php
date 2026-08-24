<?php
require_once __DIR__ . '/../_verificar_sesion.php';
$doctorId = obtenerDoctorIdDeSesion();
require_once __DIR__ . '/../../../services/admin/RecetaService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$medicamentos = $datos['medicamentos'] ?? [];
$datos['doctorId'] = $doctorId;

try {
    $service = new RecetaService();
    $recetaId = $service->registrarReceta($datos, $medicamentos);
    echo json_encode(['ok' => true, 'recetaId' => $recetaId]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al crear la receta: ' . $e->getMessage()]);
}
