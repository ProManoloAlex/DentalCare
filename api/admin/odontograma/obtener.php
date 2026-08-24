<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/OdontogramaService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$pacienteId = (int) ($_GET['pacienteId'] ?? 0);
if ($pacienteId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'pacienteId inválido.']);
    exit();
}

$service = new OdontogramaService();
echo json_encode($service->obtener($pacienteId));