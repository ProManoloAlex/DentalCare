<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/FinanzasService.php';

header('Content-Type: application/json');

$busqueda = $_GET['buscar'] ?? null;
$metodo = $_GET['metodo'] ?? null;

try {
    $service = new FinanzasService();
    echo json_encode(['ok' => true, 'pagos' => $service->listarPagos($busqueda, $metodo)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al listar pagos: ' . $e->getMessage()]);
}
