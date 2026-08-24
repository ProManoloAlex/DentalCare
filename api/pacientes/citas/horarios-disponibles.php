<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/pacientes/CitaService.php';

header('Content-Type: application/json');

obtenerPacienteIdDeSesion();

$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$servicioId = (int) ($_GET['servicio_id'] ?? 0);
$fecha = $_GET['fecha'] ?? '';

if ($doctorId <= 0 || $servicioId <= 0 || empty($fecha)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros (doctor_id, servicio_id, fecha).']);
    exit();
}

$service = new CitaService();
echo json_encode($service->listarHorariosDisponibles($doctorId, $fecha, $servicioId));