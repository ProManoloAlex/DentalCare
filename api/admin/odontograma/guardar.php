<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/OdontogramaService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$pacienteId = (int) ($body['pacienteId'] ?? 0);
$dientes = $body['dientes'] ?? [];

if ($pacienteId <= 0 || empty($dientes)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan datos.']);
    exit();
}

$service = new OdontogramaService();
$resultado = $service->guardar($pacienteId, $dientes);

if (!$resultado['ok']) {
    http_response_code(400);
}
echo json_encode($resultado);