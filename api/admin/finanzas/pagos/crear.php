<?php
require_once __DIR__ . '/../../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../../services/admin/FinanzasService.php';

// Nota: este endpoint vive bajo /api/admin/, que ya solo es accesible
// para el rol 'doctor' (por _verificar_sesion.php, igual que el resto
// de los módulos admin) -- por eso no se valida el rol otra vez aquí.
// El paciente jamás puede llegar a esta ruta desde su propio portal.

header('Content-Type: application/json');

$datos = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    $service = new FinanzasService();
    $pagoId = $service->registrarPago([
        'pacienteId' => (int) ($datos['pacienteId'] ?? 0),
        'tratamientoId' => !empty($datos['tratamientoId']) ? (int) $datos['tratamientoId'] : null,
        'citaId' => !empty($datos['citaId']) ? (int) $datos['citaId'] : null,
        'monto' => $datos['monto'] ?? null,
        'metodo' => $datos['metodo'] ?? null,
        'fechaPago' => $datos['fechaPago'] ?? null,
    ]);
    echo json_encode(['ok' => true, 'pagoId' => $pagoId]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al registrar el pago: ' . $e->getMessage()]);
}