<?php

require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/PacienteService.php';

header('Content-Type: application/json');
verificarSesionDoctor();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del paciente.']);
    exit;
}

$servicio = new PacienteService();
echo json_encode($servicio->obtenerFacturacion($id));