<?php
/**
 * _verificar_sesion.php
 * Incluir al inicio de cualquier endpoint dentro de api/pacientes/.
 * Corta la ejecución con 401 si no hay sesión válida de paciente.
 * El guion bajo en el nombre indica que este archivo no se llama
 * directamente desde el frontend, solo se incluye con require_once.
 */

require_once __DIR__ . '/../../config/Conexion_DB.php';
require_once __DIR__ . '/../../config/SesionHelper.php';

function obtenerPacienteIdDeSesion(): int {
    _verificarRolEnSesion('paciente');

    $conexion = Conexion::obtenConexion();
    $stmt = $conexion->prepare("SELECT id FROM pacientes WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $paciente = $stmt->fetch();

    if (!$paciente) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el perfil de paciente asociado a esta cuenta.']);
        exit();
    }

    return (int) $paciente['id'];
}