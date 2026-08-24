<?php
require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/DashboardService.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$periodo = $_GET['periodo'] ?? 'hoy';
if (!in_array($periodo, ['hoy', 'semana', 'mes'], true)) {
    $periodo = 'hoy';
}

$service = new DashboardService();
echo json_encode($service->obtenerResumenCompleto($periodo));