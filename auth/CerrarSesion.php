<?php
session_start();

// Si había "Recordarme" activo, hay que borrar también el token de la
// base de datos y la cookie -- si no, la próxima visita al sitio te
// volvería a loguear solo, aunque le hayas dado clic a "Cerrar Sesión"
if (isset($_SESSION['usuario_id'])) {
    require_once __DIR__ . '/../repositories/TokenRecordarmeRepository.php';
    (new TokenRecordarmeRepository())->eliminarPorUsuario((int) $_SESSION['usuario_id']);
}
setcookie('recordarme', '', ['expires' => time() - 3600, 'path' => '/']);

$_SESSION = [];
session_destroy();
header("Location: Login.php");
exit();