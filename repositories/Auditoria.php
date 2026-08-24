<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

/**
 * Uso desde cualquier Service, después de una acción exitosa:
 *
 *   require_once __DIR__ . '/../../repositories/Auditoria.php';
 *   Auditoria::registrar('finanzas', 'Registró un pago', "Pago #$pagoId por $" . $monto);
 *
 * Toma el usuario_id automáticamente de la sesión activa -- no hace
 * falta pasarlo. Si por algo falla (ej. sin sesión), no truena el
 * flujo principal: solo no se guarda el registro.
 */
class Auditoria {
    public static function registrar(string $modulo, string $accion, ?string $detalle = null): void {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $usuarioId = $_SESSION['usuario_id'] ?? null;

            $conexion = Conexion::obtenConexion();
            $stmt = $conexion->prepare(
                "INSERT INTO log_actividad (usuario_id, modulo, accion, detalle) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$usuarioId, $modulo, $accion, $detalle]);
        } catch (Exception $e) {
            // Silencioso a propósito: un fallo al auditar no debe tumbar
            // la acción real que el usuario estaba haciendo.
        }
    }
}
