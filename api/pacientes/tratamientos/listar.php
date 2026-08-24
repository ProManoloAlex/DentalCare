<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/pacientes/TratamientoService.php';

header('Content-Type: application/json');

$pacienteId = obtenerPacienteIdDeSesion();
$service = new TratamientoService();

echo json_encode($service->listarActivos($pacienteId));