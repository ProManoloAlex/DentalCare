<?php
require_once __DIR__ . '/../_verificar_sesion.php';
$pacienteId = obtenerPacienteIdDeSesion();

// Reutilizamos el mismo Service que usa el admin -- no hay lógica de
// negocio distinta, solo una consulta ya filtrada por paciente.
require_once __DIR__ . '/../../../services/admin/ConsentimientoService.php';

header('Content-Type: application/json');

try {
    $service = new ConsentimientoService();
    echo json_encode(['ok' => true, 'consentimientos' => $service->listarParaPaciente($pacienteId)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al listar tus consentimientos: ' . $e->getMessage()]);
}
