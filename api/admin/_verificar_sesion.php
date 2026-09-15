<?php
/**
 * _verificar_sesion.php (admin)
 * Incluir al inicio de cualquier endpoint dentro de api/admin/.
 * Corta la ejecución con 401 si no hay sesión válida de doctor.
 */

require_once __DIR__ . '/../../config/Conexion_DB.php';
require_once __DIR__ . '/../../config/SesionHelper.php';

function verificarSesionDoctor(): void {
    _verificarRolEnSesion('doctor');
}

function obtenerDoctorIdDeSesion(): int {
    verificarSesionDoctor();

    $conexion = Conexion::obtenConexion();
    $stmt = $conexion->prepare("SELECT id FROM doctores WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el perfil de doctor asociado a esta cuenta.']);
        exit();
    }

    return (int) $doctor['id'];
}