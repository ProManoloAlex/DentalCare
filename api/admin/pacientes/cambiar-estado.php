<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/PacienteService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$pacienteId = (int) ($body['id'] ?? 0);
$activo = (bool) ($body['activo'] ?? false);

if ($pacienteId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'id inválido.']);
    exit();
}

$service = new PacienteService();
echo json_encode($service->cambiarEstado($pacienteId, $activo));