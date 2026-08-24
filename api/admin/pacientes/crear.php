<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/PacienteService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);

$service = new PacienteService();
$resultado = $service->crear([
    'nombre'             => trim($body['nombre'] ?? ''),
    'email'              => trim($body['email'] ?? ''),
    'telefono'           => trim($body['telefono'] ?? ''),
    'fechaNacimiento'    => $body['fechaNacimiento'] ?? '',
    'genero'             => $body['genero'] ?? null,
    'direccion'          => $body['direccion'] ?? null,
    'tipoSangre'         => $body['tipoSangre'] ?? null,
    'alergias'           => $body['alergias'] ?? null,
    'contactoEmergencia' => $body['contactoEmergencia'] ?? null,
], !empty($body['darAccesoPortal']) ? ($body['contrasenaPortal'] ?? null) : null);

if (!$resultado['ok']) {
    http_response_code(400);
}

echo json_encode($resultado);