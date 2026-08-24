<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/FinanzasService.php';

header('Content-Type: application/json');

$pacienteId = (int) ($_GET['paciente_id'] ?? 0);

if ($pacienteId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el paciente_id.']);
    exit;
}

try {
    $service = new FinanzasService();
    echo json_encode(['ok' => true, 'saldos' => $service->obtenerSaldosDePaciente($pacienteId)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al obtener saldos: ' . $e->getMessage()]);
}