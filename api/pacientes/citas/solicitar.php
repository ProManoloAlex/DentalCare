<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/pacientes/CitaService.php';

header('Content-Type: application/json');

$pacienteId = obtenerPacienteIdDeSesion();
$body = json_decode(file_get_contents('php://input'), true);

$servicioId = (int) ($body['servicio_id'] ?? 0);
$doctorId   = (int) ($body['doctor_id'] ?? 0);
$fecha      = $body['fecha'] ?? '';
$hora       = $body['hora'] ?? '';
$notas      = $body['notas'] ?? null;

if ($servicioId <= 0 || $doctorId <= 0 || empty($fecha) || empty($hora)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan datos requeridos.']);
    exit();
}

$service = new CitaService();
$resultado = $service->solicitarCita($pacienteId, $doctorId, $servicioId, $fecha, $hora, $notas);

if (!$resultado['ok']) {
    http_response_code(400);
}

echo json_encode($resultado);