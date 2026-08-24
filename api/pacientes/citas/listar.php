<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/pacientes/CitaService.php';

header('Content-Type: application/json');

$pacienteId = obtenerPacienteIdDeSesion();
$service = new CitaService();

$estado = $_GET['estado'] ?? 'proximas';

if ($estado === 'completada') {
    echo json_encode($service->listarCompletadas($pacienteId));
} else {
    echo json_encode($service->listarProximas($pacienteId));
}