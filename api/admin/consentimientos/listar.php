<?php
require_once __DIR__ . '/../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../services/admin/ConsentimientoService.php';

header('Content-Type: application/json');

$busqueda = $_GET['buscar'] ?? null;
$estado = $_GET['estado'] ?? null;

try {
    $service = new ConsentimientoService();
    echo json_encode(['ok' => true, 'consentimientos' => $service->listar($busqueda, $estado)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al listar consentimientos: ' . $e->getMessage()]);
}
