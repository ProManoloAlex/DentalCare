<?php
require_once __DIR__ . '/../_verificar_sesion.php';

header('Content-Type: application/json');

obtenerPacienteIdDeSesion();

$conexion = Conexion::obtenConexion();
$stmt = $conexion->query(
    "SELECT d.id, u.nombre, d.especialidad 
     FROM doctores d 
     JOIN usuarios u ON d.usuario_id = u.id 
     WHERE u.activo = 1 
     ORDER BY u.nombre"
);
echo json_encode($stmt->fetchAll());