<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
require_once __DIR__ . '/../../../../services/admin/TratamientoService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$busqueda = $_GET['buscar'] ?? null;
$estadoParam = $_GET['estado'] ?? null;
$estado = $estadoParam === 'En Progreso' ? 'activo' : ($estadoParam === 'Completado' ? 'completado' : null);
$orden = $_GET['orden'] ?? 'recientes';

$service = new TratamientoService();

echo json_encode([
    'resumen'       => $service->obtenerResumen(),
    'tratamientos'  => $service->listar($busqueda, $estado, $orden),
]);