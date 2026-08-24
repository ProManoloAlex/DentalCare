<?php
require_once __DIR__ . '/../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../services/admin/ConsentimientoService.php';

header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el id.']);
    exit;
}

try {
    $service = new ConsentimientoService();
    $detalle = $service->obtenerDetalle($id);

    if (!$detalle) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'El consentimiento no existe.']);
        exit;
    }

    echo json_encode(['ok' => true, 'consentimiento' => $detalle]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al obtener el detalle: ' . $e->getMessage()]);
}
