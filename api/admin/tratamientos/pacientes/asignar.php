<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
require_once __DIR__ . '/../../../../services/admin/TratamientoService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);

$service = new TratamientoService();
$resultado = $service->asignar([
    'pacienteId'    => (int) ($body['pacienteId'] ?? 0),
    'doctorId'      => (int) ($body['doctorId'] ?? 0),
    'servicioId'    => (int) ($body['servicioId'] ?? 0),
    'nombre'        => trim($body['nombre'] ?? ''),
    'categoria'     => $body['categoria'] ?? '',
    'descripcion'   => $body['descripcion'] ?? null,
    'diagnostico'   => trim($body['diagnostico'] ?? ''),
    'notas'         => $body['notas'] ?? null,
    'sesionesTotal' => (int) ($body['sesionesTotal'] ?? 1),
    'fechaInicio'   => $body['fechaInicio'] ?? '',
    'horaInicio'    => $body['horaInicio'] ?? '',
    'costoTotal'    => (float) ($body['costoTotal'] ?? 0),
    'pagoInicial'   => (float) ($body['pagoInicial'] ?? 0),
    'consentimientoTipo'   => $body['consentimientoTipo'] ?? null,
    'consentimientoTitulo' => trim($body['consentimientoTitulo'] ?? ''),
    'consentimientoTexto'  => trim($body['consentimientoTexto'] ?? ''),
]);

if (!$resultado['ok']) {
    http_response_code(400);
}
echo json_encode($resultado);