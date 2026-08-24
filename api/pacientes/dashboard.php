<?php
require_once __DIR__ . '/_verificar_sesion.php';
require_once __DIR__ . '/../../services/pacientes/CitaService.php';
require_once __DIR__ . '/../../services/pacientes/TratamientoService.php';

header('Content-Type: application/json');

$pacienteId = obtenerPacienteIdDeSesion();

$citaService = new CitaService();
$tratamientoService = new TratamientoService();

$saldoCitas = $citaService->calcularSaldoPendienteBruto($pacienteId);
$saldoTratamientos = $tratamientoService->calcularSaldoPendiente($pacienteId);

echo json_encode([
    'citas_proximas'        => $citaService->contarProximas($pacienteId),
    'tratamientos_activos'  => $tratamientoService->contarActivos($pacienteId),
    'saldo_pendiente'       => number_format($saldoCitas + $saldoTratamientos, 2),
]);