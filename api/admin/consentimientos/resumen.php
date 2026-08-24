<?php
require_once __DIR__ . '/../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../services/admin/ConsentimientoService.php';

header('Content-Type: application/json');

try {
    $service = new ConsentimientoService();
    echo json_encode(['ok' => true, 'resumen' => $service->obtenerResumen()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al obtener el resumen: ' . $e->getMessage()]);
}
