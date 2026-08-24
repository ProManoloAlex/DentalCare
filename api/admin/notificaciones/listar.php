<?php
require_once __DIR__ . '/../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../services/admin/NotificacionService.php';

header('Content-Type: application/json');

try {
    $service = new NotificacionService();
    echo json_encode(['ok' => true] + $service->obtenerParaCampana());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al obtener notificaciones: ' . $e->getMessage()]);
}
