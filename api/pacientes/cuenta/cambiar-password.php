<?php
require_once __DIR__ . '/../_verificar_sesion.php';
obtenerPacienteIdDeSesion(); // solo para confirmar que hay sesión de paciente válida
require_once __DIR__ . '/../../../services/pacientes/CuentaService.php';

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];
$usuarioId = (int) $_SESSION['usuario_id'];

try {
    $service = new CuentaService();
    $service->cambiarPassword($usuarioId, $datos['actual'] ?? '', $datos['nueva'] ?? '');
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al cambiar la contraseña: ' . $e->getMessage()]);
}
