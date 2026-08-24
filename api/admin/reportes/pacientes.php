<?php
require_once __DIR__ . '/../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../services/admin/ReporteService.php';

header('Content-Type: application/json');

// Nota: a diferencia de los otros 4 reportes, este no depende del
// periodo -- retención, distribución por edad/género, etc. son
// métricas globales de toda la cartera de pacientes.

try {
    $service = new ReporteService();
    echo json_encode(['ok' => true, 'reporte' => $service->obtenerPacientes()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al generar el reporte: ' . $e->getMessage()]);
}
