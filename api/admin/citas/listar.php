<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/CitaService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$busqueda = $_GET['buscar'] ?? null;
$estado = $_GET['estado'] ?? null;
$doctorId = !empty($_GET['doctor_id']) ? (int) $_GET['doctor_id'] : null;

$service = new CitaService();
echo json_encode($service->listarTodas($busqueda, $estado, $doctorId));