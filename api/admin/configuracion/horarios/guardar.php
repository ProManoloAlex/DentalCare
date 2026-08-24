<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/ConfiguracionService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$horarios = $datos['horarios'] ?? [];

try {
    $service = new ConfiguracionService();
    $service->guardarHorarios($horarios);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al guardar horarios: ' . $e->getMessage()]);
}
