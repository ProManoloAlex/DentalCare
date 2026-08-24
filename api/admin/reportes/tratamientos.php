<?php
require_once __DIR__ . '/../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../services/admin/ReporteService.php';

header('Content-Type: application/json');

$periodo = $_GET['periodo'] ?? null;

try {
    $service = new ReporteService();
    echo json_encode(['ok' => true, 'reporte' => $service->obtenerTratamientos($periodo)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al generar el reporte: ' . $e->getMessage()]);
}
