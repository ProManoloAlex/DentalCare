<?php

require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/RecordatorioService.php';

header('Content-Type: application/json');
verificarSesionDoctor();

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$citaId = (int) ($datos['citaId'] ?? 0);
$reglaId = (int) ($datos['reglaId'] ?? 0);

if (!$citaId || !$reglaId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan citaId o reglaId.']);
    exit;
}

$servicio = new RecordatorioService();

try {
    echo json_encode($servicio->enviarRecordatorio($citaId, $reglaId));
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}