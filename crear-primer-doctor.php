<?php
/**
 * crear-primer-doctor.php
 * Se corre UNA SOLA VEZ después de importar instalacion.sql, para crear
 * la primera cuenta de doctor con la que vas a poder iniciar sesión.
 *
 * CÓMO USARLO:
 *   1. Copia este archivo a la raíz del proyecto (junto a index.php)
 *   2. Cambia los 4 valores de abajo (NOMBRE, CORREO, PASSWORD, ESPECIALIDAD)
 *   3. Ábrelo en el navegador: http://localhost/tu-proyecto/crear-primer-doctor.php
 *   4. En cuanto veas "Doctor creado correctamente", BORRA este archivo
 *      del proyecto -- no debe quedarse ahí, ya cumplió su función y
 *      dejarlo sería un hueco de seguridad (cualquiera podría crear
 *      cuentas de doctor nuevas solo visitando la URL).
 */

// ============================================================
// CAMBIA ESTOS 4 VALORES ANTES DE CORRERLO
// ============================================================
$NOMBRE = 'Manuel Alex';
$CORREO = 'doctor@tuclinica.com';
$PASSWORD = 'CambiaEstaContraseña123';
$ESPECIALIDAD = 'Administración General';
// ============================================================

require_once __DIR__ . '/config/Conexion_DB.php';

$conexion = Conexion::obtenConexion();

// Evita crear un doctor duplicado si por accidente corres esto 2 veces
$stmt = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ?");
$stmt->execute([$CORREO]);
if ($stmt->fetch()) {
    die("Ya existe una cuenta con ese correo. Si quieres crear otro doctor, cambia el correo arriba.");
}

$conexion->beginTransaction();
try {
    $hash = password_hash($PASSWORD, PASSWORD_BCRYPT);

    $stmtUsuario = $conexion->prepare(
        "INSERT INTO usuarios (nombre, correo, contrasenna, rol) VALUES (?, ?, ?, 'doctor')"
    );
    $stmtUsuario->execute([$NOMBRE, $CORREO, $hash]);
    $usuarioId = (int) $conexion->lastInsertId();

    $stmtDoctor = $conexion->prepare(
        "INSERT INTO doctores (usuario_id, especialidad, consultorio) VALUES (?, ?, 'Consultorio 1')"
    );
    $stmtDoctor->execute([$usuarioId, $ESPECIALIDAD]);

    $conexion->commit();

    echo "<h2>✅ Doctor creado correctamente</h2>";
    echo "<p>Correo: <strong>$CORREO</strong></p>";
    echo "<p>Ya puedes iniciar sesión en <a href='auth/Login.php'>auth/Login.php</a> con ese correo y la contraseña que pusiste arriba.</p>";
    echo "<p style='color:#dc2626; font-weight:bold;'>⚠️ Borra este archivo (crear-primer-doctor.php) del proyecto ahora mismo.</p>";
} catch (Exception $e) {
    $conexion->rollBack();
    echo "Error al crear el doctor: " . $e->getMessage();
}
