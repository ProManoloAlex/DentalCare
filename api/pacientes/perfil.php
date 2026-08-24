<?php
require_once __DIR__ . '/_verificar_sesion.php';

header('Content-Type: application/json');

obtenerPacienteIdDeSesion(); // valida que haya sesión de paciente

echo json_encode([
    'nombre' => $_SESSION['usuario_nombre'],
    'correo' => $_SESSION['usuario_correo'],
]);