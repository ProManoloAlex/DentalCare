<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/RecordatorioService.php';

header('Content-Type: application/json');

try {
    $service = new RecordatorioService();
    echo json_encode(['ok' => true, 'canales' => $service->obtenerCanales()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al obtener la configuración: ' . $e->getMessage()]);
}
