<?php
/**
 * _verificar_sesion.php (admin)
 * Incluir al inicio de cualquier endpoint dentro de api/admin/.
 * Corta la ejecución con 401 si no hay sesión válida de doctor.
 */

require_once __DIR__ . '/../../config/Conexion_DB.php';

function verificarSesionDoctor(): void {
    session_start();

    if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'doctor') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'mensaje' => 'No hay sesión de doctor activa.']);
        exit();
    }
}

/**
 * Devuelve el doctores.id del doctor en sesión, para módulos que
 * necesiten filtrar "solo mis citas" en vez de "todas las de la clínica".
 */
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