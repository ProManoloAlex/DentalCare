<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/pacientes/TratamientoService.php';
require_once __DIR__ . '/../../../services/pacientes/CitaService.php';

header('Content-Type: application/json');

$pacienteId = obtenerPacienteIdDeSesion();

$tratamientoService = new TratamientoService();
$citaService = new CitaService();

$cuenta = $tratamientoService->obtenerCuenta($pacienteId);
$citasNoPagadas = $citaService->listarNoPagadas($pacienteId);

// El saldo total ahora combina tratamientos + citas sueltas sin pagar,
// para que coincida exacto con el número del dashboard.
$saldoCitas = array_sum(array_map(fn($c) => (float) str_replace(',', '', $c['costo']), $citasNoPagadas));
$saldoTotal = (float) str_replace(',', '', $cuenta['saldo_total']) + $saldoCitas;

echo json_encode([
    'saldo_total'      => number_format($saldoTotal, 2),
    'detalle'          => $cuenta['detalle'],
    'citas_sin_pagar'  => $citasNoPagadas,
]);