<?php
require_once __DIR__ . '/_verificar_sesion.php';

verificarSesionDoctor();

header('Content-Type: application/json');

echo json_encode([
    'nombre' => $_SESSION['usuario_nombre'],
    'correo' => $_SESSION['usuario_correo'],
]);