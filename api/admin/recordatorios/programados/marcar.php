<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/RecordatorioService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    $service = new RecordatorioService();
    $id = $service->registrarEnvio([
        'citaId' => $datos['citaId'] ?? null,
        'reglaId' => $datos['reglaId'] ?? null,
        'pacienteId' => $datos['pacienteId'] ?? null,
        'canal' => $datos['canal'] ?? 'email',
        'mensaje' => $datos['mensaje'] ?? '',
        'fechaProgramada' => $datos['fechaProgramada'] ?? date('Y-m-d H:i:s'),
        'estado' => $datos['estado'] ?? 'fallido',
    ]);
    echo json_encode(['ok' => true, 'recordatorioId' => $id]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al registrar el envío: ' . $e->getMessage()]);
}
