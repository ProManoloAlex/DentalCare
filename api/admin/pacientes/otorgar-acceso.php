<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/PacienteService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$pacienteId = (int) ($body['id'] ?? 0);
$contrasena = $body['contrasena'] ?? '';

if ($pacienteId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'id inválido.']);
    exit();
}

if (empty($contrasena)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'La contraseña es obligatoria.']);
    exit();
}

$service = new PacienteService();
$resultado = $service->otorgarAcceso($pacienteId, $contrasena);

if (!$resultado['ok']) {
    http_response_code(404);
}

echo json_encode($resultado);