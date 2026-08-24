<?php

require_once __DIR__ . '/../services/pacientes/RecuperacionPasswordService.php';

$correo = $_POST['correo'] ?? '';

$servicio = new RecuperacionPasswordService();
$resultado = $servicio->solicitar($correo);

header('Location: recuperar-password.html?enviado=1');
exit;