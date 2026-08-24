<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/CitaService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$citaId = (int) ($body['id'] ?? 0);

if ($citaId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'id inválido.']);
    exit();
}

$service = new CitaService();
$resultado = $service->actualizar($citaId, [
    'pacienteId' => (int) ($body['pacienteId'] ?? 0),
    'doctorId'   => (int) ($body['doctorId'] ?? 0),
    'servicioId' => (int) ($body['servicioId'] ?? 0),
    'fecha'      => $body['fecha'] ?? '',
    'hora'       => $body['hora'] ?? '',
    'estado'     => $body['estado'] ?? 'pendiente',
    'notas'      => $body['notas'] ?? null,
]);

if (!$resultado['ok']) {
    http_response_code(400);
}

echo json_encode($resultado);