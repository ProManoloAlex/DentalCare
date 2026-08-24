<?php
require_once __DIR__ . '/../../_verificar_sesion.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$conexion = Conexion::obtenConexion();
$stmt = $conexion->query(
    "SELECT p.id, u.nombre, p.telefono 
     FROM pacientes p 
     JOIN usuarios u ON p.usuario_id = u.id 
     WHERE u.activo = 1 
     ORDER BY u.nombre"
);
echo json_encode($stmt->fetchAll());