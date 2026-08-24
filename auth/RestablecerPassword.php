<?php

require_once __DIR__ . '/../services/pacientes/RecuperacionPasswordService.php';

$token     = $_POST['token'] ?? '';
$nueva     = $_POST['nueva'] ?? '';
$confirmar = $_POST['confirmar'] ?? '';

$servicio = new RecuperacionPasswordService();
$resultado = $servicio->restablecer($token, $nueva, $confirmar);

if ($resultado['ok']) {
    header('Location: Login.php?reset=exitoso');
} else {
    $mensaje = urlencode($resultado['mensaje']);
    header("Location: restablecer-password.html?token=" . urlencode($token) . "&error=$mensaje");
}
exit;