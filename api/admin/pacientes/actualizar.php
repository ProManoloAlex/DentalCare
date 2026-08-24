<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/PacienteService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$pacienteId = (int) ($body['id'] ?? 0);

if ($pacienteId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'id inválido.']);
    exit();
}

$service = new PacienteService();
$resultado = $service->actualizar($pacienteId, [
    'nombre'             => trim($body['nombre'] ?? ''),
    'email'              => trim($body['email'] ?? ''),
    'telefono'           => trim($body['telefono'] ?? ''),
    'fechaNacimiento'    => $body['fechaNacimiento'] ?? '',
    'genero'             => $body['genero'] ?? null,
    'direccion'          => $body['direccion'] ?? null,
    'tipoSangre'         => $body['tipoSangre'] ?? null,
    'alergias'           => $body['alergias'] ?? null,
    'contactoEmergencia' => $body['contactoEmergencia'] ?? null,
]);

if (!$resultado['ok']) {
    http_response_code(400);
}

echo json_encode($resultado);