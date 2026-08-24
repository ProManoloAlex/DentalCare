<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
require_once __DIR__ . '/../../../../services/admin/ServicioService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'id inválido.']);
    exit();
}

$service = new ServicioService();
$resultado = $service->obtenerPorId($id);

if (!$resultado['ok']) {
    http_response_code(404);
}
echo json_encode($resultado);