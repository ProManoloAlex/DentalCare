<?php
require_once __DIR__ . '/../_verificar_sesion.php';
$pacienteId = obtenerPacienteIdDeSesion();

require_once __DIR__ . '/../../../services/admin/RecetaService.php';

header('Content-Type: application/json');

try {
    $service = new RecetaService();
    echo json_encode(['ok' => true, 'recetas' => $service->listarParaPaciente($pacienteId)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al listar tus recetas: ' . $e->getMessage()]);
}