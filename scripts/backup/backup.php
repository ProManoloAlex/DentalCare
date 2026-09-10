<?php
/**
 * scripts/backup/backup.php
 *
 * Exporta toda la base de datos (clinica_dental) como un dump SQL comprimido
 * y lo entrega como descarga HTTP, protegido con una clave secreta.
 *
 * Pensado para hosting sin acceso SSH/mysqldump (ej. ProFreeHost). Un disparador
 * externo (cron-job.org, GitHub Actions programado, etc.) llama a esta URL
 * periódicamente y guarda la respuesta como archivo .sql.gz.
 *
 * USO:
 *   https://tudominio.com/scripts/backup/backup.php?key=TU_CLAVE_SECRETA
 *
 * REQUIERE en el .env:
 *   BACKUP_SECRET_KEY=una-cadena-larga-y-aleatoria-dificil-de-adivinar
 *
 * IMPORTANTE:
 *   - Genera BACKUP_SECRET_KEY con algo como bin2hex(random_bytes(32)) y
 *     ponlo tú mismo en el .env del servidor — nunca lo compartas en chat.
 *   - Sin la clave correcta, el script responde 403 y no hace nada.
 */

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/Conexion_DB.php';

$esCLI = (php_sapi_name() === 'cli');

// --- 1. Verificar clave secreta (solo aplica si viene por navegador/HTTP) ---
// Si corre por línea de comandos (cron, tarea programada), no hace falta
// clave: ya es una ejecución de confianza dentro del propio servidor, nunca
// sale a internet.
if (!$esCLI) {
    $claveEsperada = $_ENV['BACKUP_SECRET_KEY'] ?? null;
    $claveRecibida = $_GET['key'] ?? null;

    if (!$claveEsperada || !$claveRecibida || !hash_equals($claveEsperada, $claveRecibida)) {
        http_response_code(403);
        die('Acceso denegado.');
    }
}

// --- 2. Generar el dump SQL completo ---
function generarDumpSQL(PDO $pdo, string $dbName): string {
    $sql = "-- Backup de $dbName\n";
    $sql .= "-- Generado: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET NAMES utf8mb4;\n\n";

    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tablas as $tabla) {
        // Estructura de la tabla
        $crear = $pdo->query("SHOW CREATE TABLE `$tabla`")->fetch(PDO::FETCH_ASSOC);
        $sql .= "-- Estructura de `$tabla`\n";
        $sql .= "DROP TABLE IF EXISTS `$tabla`;\n";
        $sql .= $crear['Create Table'] . ";\n\n";

        // Datos de la tabla
        $filas = $pdo->query("SELECT * FROM `$tabla`");
        $totalFilas = 0;

        foreach ($filas as $fila) {
            if ($totalFilas === 0) {
                $sql .= "-- Datos de `$tabla`\n";
            }
            $columnas = array_map(fn($c) => "`$c`", array_keys($fila));
            $valores = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string) $v);
            }, array_values($fila));

            $sql .= "INSERT INTO `$tabla` (" . implode(', ', $columnas) . ") VALUES ("
                  . implode(', ', $valores) . ");\n";
            $totalFilas++;
        }

        if ($totalFilas > 0) {
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $sql;
}

// --- 3. Registrar en el log (éxito o error) ---
function registrarLog(string $mensaje): void {
    $carpetaLogs = __DIR__ . '/../logs'; // scripts/logs/
    if (!is_dir($carpetaLogs)) {
        mkdir($carpetaLogs, 0777, true);
    }
    $logPath = $carpetaLogs . '/backups.log';
    $linea = '[' . date('Y-m-d H:i:s') . "] $mensaje\n";
    file_put_contents($logPath, $linea, FILE_APPEND | LOCK_EX);
}

// --- 3.1 Guardar una copia del backup en el mismo servidor (respaldo rápido) ---
//
// Nota: esta parte funciona exactamente igual sin importar si backup.php
// corre en tu servidor local de pruebas o en el hosting real — solo depende
// de la carpeta scripts/backup/respaldos/, que es relativa al propio
// archivo. No hace falta ningún ajuste distinto entre local y hosting; el
// mismo código sirve para los dos casos.
function guardarCopiaEnServidor(string $comprimido, string $nombreArchivo): void {
    $carpetaRespaldos = __DIR__ . '/respaldos'; // scripts/backup/respaldos/

    if (!is_dir($carpetaRespaldos)) {
        mkdir($carpetaRespaldos, 0777, true);
    }

    // Evitar guardar varias copias el mismo día si el script corre más de
    // una vez (por ejemplo, si pruebas manualmente varias veces seguidas).
    $yaHayCopiaHoy = glob($carpetaRespaldos . '/backup_' . date('Ymd') . '_*.sql.gz');

    if (!empty($yaHayCopiaHoy)) {
        registrarLog('INFO - Ya existe una copia en el servidor para hoy, no se duplica: ' . basename($yaHayCopiaHoy[0]));
        return;
    }

    $rutaDestino = $carpetaRespaldos . '/' . $nombreArchivo;
    file_put_contents($rutaDestino, $comprimido);
    registrarLog('OK - Copia guardada en el servidor: ' . $nombreArchivo);

    limpiarCopiasAntiguas($carpetaRespaldos, 30); // conserva ~1 mes
}

// --- 3.2 Borrar copias de más de $diasRetencion días, para no saturar la cuota de espacio ---
function limpiarCopiasAntiguas(string $carpetaRespaldos, int $diasRetencion): void {
    $limite = time() - ($diasRetencion * 86400);
    $archivos = glob($carpetaRespaldos . '/backup_*.sql.gz');

    foreach ($archivos as $archivo) {
        if (filemtime($archivo) < $limite) {
            unlink($archivo);
            registrarLog('INFO - Copia antigua borrada del servidor (>' . $diasRetencion . ' días): ' . basename($archivo));
        }
    }
}

// --- 4. Ejecutar ---
try {
    $pdo = Conexion::obtenConexion();
    $dbName = $_ENV['DB_NAME'] ?? 'clinica_dental';

    $dumpSQL = generarDumpSQL($pdo, $dbName);
    $comprimido = gzencode($dumpSQL, 9);

    $nombreArchivo = 'backup_' . date('Ymd_His') . '.sql.gz';

    registrarLog("OK - backup generado ($nombreArchivo, " . strlen($comprimido) . " bytes)");

    // Guardar también una copia rotativa en el propio servidor (respaldo rápido)
    guardarCopiaEnServidor($comprimido, $nombreArchivo);

    if ($esCLI) {
        // Ejecución por cron/tarea programada: con guardar el respaldo basta,
        // no hay navegador esperando una descarga.
        echo "Backup generado y respaldado: $nombreArchivo\n";
        exit(0);
    }

    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($comprimido));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $comprimido;
    exit;

} catch (Throwable $e) {
    registrarLog('ERROR - ' . $e->getMessage());
    http_response_code(500);
    die('Error al generar el backup. Revisa backups.log.');
}