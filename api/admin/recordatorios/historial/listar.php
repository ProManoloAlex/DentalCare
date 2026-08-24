<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/RecordatorioService.php';

header('Content-Type: application/json');

$busqueda = $_GET['buscar'] ?? null;
$canal = $_GET['canal'] ?? null;
$estado = $_GET['estado'] ?? null;

try {
    $service = new RecordatorioService();
    echo json_encode(['ok' => true, 'historial' => $service->listarHistorial($busqueda, $canal, $estado)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al listar historial: ' . $e->getMessage()]);
}
