<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/PacienteService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$pacienteId = (int) ($_GET['id'] ?? 0);

if ($pacienteId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'id inválido.']);
    exit();
}

$service = new PacienteService();
$resultado = $service->obtenerDetalle($pacienteId);

if (!$resultado['ok']) {
    http_response_code(404);
}

echo json_encode($resultado);