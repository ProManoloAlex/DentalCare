<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/CitaService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$citaId = (int) ($_GET['id'] ?? 0);

if ($citaId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'id inválido.']);
    exit();
}

$service = new CitaService();
$resultado = $service->obtenerParaEditar($citaId);

if (!$resultado['ok']) {
    http_response_code(404);
}

echo json_encode($resultado);