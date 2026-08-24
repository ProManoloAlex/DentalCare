<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
require_once __DIR__ . '/../../../../services/admin/TratamientoService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$id = (int) ($body['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del tratamiento.']);
    exit;
}

$service = new TratamientoService();
$resultado = $service->cancelar($id);

echo json_encode($resultado);