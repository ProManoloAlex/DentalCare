<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
require_once __DIR__ . '/../../../../services/admin/ServicioService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$id = (int) ($body['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'id inválido.']);
    exit();
}

$service = new ServicioService();
$resultado = $service->eliminar($id);

if (!$resultado['ok']) {
    http_response_code(409); // Conflict: existe pero no se puede borrar por su relación con otras tablas
}
echo json_encode($resultado);