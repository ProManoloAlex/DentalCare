<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/ConfiguracionService.php';

header('Content-Type: application/json');

try {
    $service = new ConfiguracionService();
    echo json_encode(['ok' => true, 'doctores' => $service->listarDoctores()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al listar doctores: ' . $e->getMessage()]);
}
