<?php
require_once __DIR__ . '/Conexion_DB.php';
require_once __DIR__ . '/../repositories/TokenRecordarmeRepository.php';

function _verificarRolEnSesion(string $rolEsperado): void {
    session_start();

    if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== $rolEsperado) {
        // No hay sesión activa -- antes de rendirnos, checamos si hay
        // una cookie "recordarme" válida para restaurar la sesión sola
        if (!_intentarRestaurarSesionDesdeCookie($rolEsperado)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'mensaje' => "No hay sesión de {$rolEsperado} activa."]);
            exit();
        }
    }
}

function _intentarRestaurarSesionDesdeCookie(string $rolEsperado): bool {
    if (!isset($_COOKIE['recordarme']) || !str_contains($_COOKIE['recordarme'], ':')) {
        return false;
    }

    [$usuarioId, $token] = explode(':', $_COOKIE['recordarme'], 2);
    $usuarioId = (int) $usuarioId;
    $tokenHash = hash('sha256', $token);

    $tokensRepo = new TokenRecordarmeRepository();
    if (!$tokensRepo->obtenerValido($usuarioId, $tokenHash)) {
        return false;
    }

    $conexion = Conexion::obtenConexion();
    $stmt = $conexion->prepare("SELECT id, nombre, correo, rol FROM usuarios WHERE id = ? AND activo = 1 AND rol = ?");
    $stmt->execute([$usuarioId, $rolEsperado]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id']     = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_correo'] = $usuario['correo'];
    $_SESSION['usuario_rol']    = $usuario['rol'];

    return true;
}