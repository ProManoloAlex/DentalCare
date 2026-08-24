<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/PacienteService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$busqueda = $_GET['buscar'] ?? null;
$estado = $_GET['estado'] ?? null;
$orden = $_GET['orden'] ?? 'nombre';

$service = new PacienteService();

echo json_encode([
    'resumen'   => $service->obtenerResumen(),
    'pacientes' => $service->listar($busqueda, $estado, $orden),
]);