<?php
require_once __DIR__ . '/../_verificar_sesion.php';
$pacienteId = obtenerPacienteIdDeSesion();
require_once __DIR__ . '/../../../services/pacientes/CuentaService.php';

header('Content-Type: application/json');

try {
    $service = new CuentaService();
    echo json_encode(['ok' => true, 'cuenta' => $service->obtenerSaldo($pacienteId)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al obtener tu cuenta: ' . $e->getMessage()]);
}
