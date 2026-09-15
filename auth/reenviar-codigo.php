<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../services/RegistroService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit();
}

$body   = json_decode(file_get_contents('php://input'), true);
$correo = filter_var(trim($body['correo'] ?? ''), FILTER_SANITIZE_EMAIL);

$service = new RegistroService();
echo json_encode($service->reenviarCodigo($correo));