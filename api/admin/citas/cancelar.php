<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/CitaService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$id = (int) ($body['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el id de la cita.']);
    exit;
}

$servicio = new CitaService();
$resultado = $servicio->cancelar($id);

echo json_encode($resultado);