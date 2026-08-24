<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/CitaService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);

$service = new CitaService();
$resultado = $service->crear([
    'pacienteId'    => (int) ($body['pacienteId'] ?? 0),
    'doctorId'      => (int) ($body['doctorId'] ?? 0),
    'servicioId'    => (int) ($body['servicioId'] ?? 0),
    'tratamientoId' => !empty($body['tratamientoId']) ? (int) $body['tratamientoId'] : null,
    'fecha'         => $body['fecha'] ?? '',
    'hora'          => $body['hora'] ?? '',
    'estado'        => $body['estado'] ?? 'pendiente',
    'notas'         => $body['notas'] ?? null,
]);

if (!$resultado['ok']) {
    http_response_code(400);
}

echo json_encode($resultado);