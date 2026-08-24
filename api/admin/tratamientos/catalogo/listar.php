<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
require_once __DIR__ . '/../../../../services/admin/ServicioService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$busqueda = $_GET['buscar'] ?? null;
$categoria = $_GET['categoria'] ?? null;
$orden = $_GET['orden'] ?? 'nombre';

$service = new ServicioService();
echo json_encode($service->listar($busqueda, $categoria, $orden));