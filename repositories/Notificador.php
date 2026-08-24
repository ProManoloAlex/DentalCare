<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

/**
 * Uso desde cualquier Service, después de una acción exitosa:
 *
 *   require_once __DIR__ . '/../../repositories/Notificador.php';
 *   Notificador::disparar('Cancelación de cita', 'Cita cancelada', "Juan Pérez — Limpieza dental", $pacienteId, $citaId);
 *
 * El primer parámetro DEBE coincidir exacto con la columna "evento" de
 * notificaciones_preferencias -- si el doctor apagó el canal "app" para
 * ese evento, esto no hace nada. Igual que Auditoria: si algo falla,
 * no truena el flujo principal, solo no se guarda la notificación.
 */
class Notificador {
    public static function disparar(string $evento, string $titulo, ?string $mensaje = null, ?int $pacienteId = null, ?int $citaId = null): void {
        try {
            $conexion = Conexion::obtenConexion();

            $stmtPref = $conexion->prepare("SELECT app FROM notificaciones_preferencias WHERE evento = ?");
            $stmtPref->execute([$evento]);
            $fila = $stmtPref->fetch();

            // Si no existe una fila de preferencia para ese evento, se
            // notifica de todas formas (mejor avisar de más que perderse
            // algo por un evento mal registrado en el catálogo).
            if ($fila && !(bool) $fila['app']) {
                return;
            }

            $stmt = $conexion->prepare(
                "INSERT INTO notificaciones (tipo, titulo, mensaje, paciente_id, cita_id) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$evento, $titulo, $mensaje, $pacienteId, $citaId]);
        } catch (Exception $e) {
            // Silencioso a propósito, igual que Auditoria.
        }
    }
}
