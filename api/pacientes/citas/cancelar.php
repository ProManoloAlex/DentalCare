<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/pacientes/CitaService.php';

header('Content-Type: application/json');

$pacienteId = obtenerPacienteIdDeSesion();
$body = json_decode(file_get_contents('php://input'), true);

$citaId = (int) ($body['cita_id'] ?? 0);

if ($citaId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'cita_id inválido.']);
    exit();
}

$service = new CitaService();
$resultado = $service->cancelarCita($citaId, $pacienteId);

echo json_encode($resultado);