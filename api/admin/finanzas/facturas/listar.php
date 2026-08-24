<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/FinanzasService.php';

header('Content-Type: application/json');

$busqueda = $_GET['buscar'] ?? null;
$estado = $_GET['estado'] ?? null;

try {
    $service = new FinanzasService();
    echo json_encode(['ok' => true, 'facturas' => $service->listarFacturas($busqueda, $estado)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al listar facturas: ' . $e->getMessage()]);
}