<?php
require_once __DIR__ . '/../../_verificar_sesion.php';

verificarSesionDoctor();

header('Content-Type: application/json');

$conexion = Conexion::obtenConexion();
$stmt = $conexion->query("SELECT id, nombre, categoria, precio, duracion_min FROM servicios WHERE activo = 1 ORDER BY nombre");
echo json_encode($stmt->fetchAll());