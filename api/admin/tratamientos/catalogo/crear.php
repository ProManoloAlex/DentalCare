<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
require_once __DIR__ . '/../../../../services/admin/ServicioService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);

$service = new ServicioService();
$resultado = $service->crear([
    'nombre'      => trim($body['nombre'] ?? ''),
    'categoria'   => $body['categoria'] ?? '',
    'descripcion' => $body['descripcion'] ?? null,
    'duracionMin' => (int) ($body['duracionMin'] ?? 0),
    'precio'      => (float) ($body['precio'] ?? 0),
]);

if (!$resultado['ok']) {
    http_response_code(400);
}
echo json_encode($resultado);