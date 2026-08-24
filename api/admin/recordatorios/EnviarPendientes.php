<?php

require_once __DIR__ . '/../_verificar_sesion.php';
require_once __DIR__ . '/../../../services/admin/RecordatorioService.php';

header('Content-Type: application/json');
verificarSesionDoctor();

$servicio = new RecordatorioService();
echo json_encode($servicio->enviarTodosPendientes());