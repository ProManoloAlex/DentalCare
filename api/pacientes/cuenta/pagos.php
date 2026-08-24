<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/pacientes/TratamientoService.php';

header('Content-Type: application/json');

$pacienteId = obtenerPacienteIdDeSesion();

$tratamientoId = (int) ($_GET['tratamiento_id'] ?? 0);

if ($tratamientoId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'tratamiento_id inválido.']);
    exit();
}

$service = new TratamientoService();
echo json_encode($service->obtenerHistorialPagos($tratamientoId, $pacienteId));