<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../services/RegistroService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit();
}

$nombre      = trim($_POST['nombre'] ?? '');
$correo      = filter_var(trim($_POST['correo'] ?? ''), FILTER_SANITIZE_EMAIL);
$contrasenna = $_POST['contrasenna'] ?? '';

if (empty($nombre) || empty($correo) || empty($contrasenna)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Por favor, rellene todos los campos obligatorios.']);
    exit();
}

$service = new RegistroService();
echo json_encode($service->registrar($nombre, $correo, $contrasenna));