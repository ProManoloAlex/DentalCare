<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/OdontogramaService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$busqueda = $_GET['buscar'] ?? null;

$service = new OdontogramaService();
echo json_encode($service->listarPacientes($busqueda));