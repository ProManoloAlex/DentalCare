<?php

require_once __DIR__ . '/Conexion_DB.php';

/**
 * Horario de la clínica -- YA NO son horas fijas iguales todos los
 * días. Se leen de la tabla "horarios_atencion" (la misma que edita
 * el doctor desde Configuración → Clínica → Horarios), para que
 * cerrar un día ahí de verdad bloquee que se puedan agendar citas
 * ese día, en vez de ser solo un aviso visual.
 *
 * La hora de comida sigue siendo la misma para todos los días por
 * ahora -- Configuración todavía no tiene una pantalla para eso.
 * Si algún día la necesitas por día, este es el único archivo que
 * habría que tocar.
 */
class HorarioClinica {
    public const COMIDA_INICIO = '14:00';
    public const COMIDA_FIN    = '15:00';

    // Cache en memoria por el tiempo de vida de esta petición HTTP,
    // para no repetir la misma consulta si se llama varias veces
    // seguidas (obtenerRazonNoDisponible + obtenerHorariosDisponibles
    // suelen consultar el mismo día).
    private static array $cache = [];

    private static function obtenerHorarioDelDia(string $fecha): ?array {
        $diaSemana = (int) (new DateTime($fecha))->format('N'); // 1=Lunes...7=Domingo, igual que en la BD

        if (array_key_exists($diaSemana, self::$cache)) {
            return self::$cache[$diaSemana];
        }

        $conexion = Conexion::obtenConexion();
        $stmt = $conexion->prepare("SELECT activo, hora_inicio, hora_fin FROM horarios_atencion WHERE dia_semana = ?");
        $stmt->execute([$diaSemana]);
        $fila = $stmt->fetch();

        self::$cache[$diaSemana] = $fila ?: null;
        return self::$cache[$diaSemana];
    }

    public static function estaAbierto(string $fecha): bool {
        $horario = self::obtenerHorarioDelDia($fecha);
        // Si no hubiera fila configurada para ese día (no debería pasar,
        // la migración siembra los 7 días), se asume abierto para no
        // tumbar el sistema completo por un dato faltante.
        return $horario === null || (bool) $horario['activo'];
    }

    public static function horaApertura(string $fecha): string {
        $horario = self::obtenerHorarioDelDia($fecha);
        return $horario && $horario['hora_inicio'] ? substr($horario['hora_inicio'], 0, 5) : '08:00';
    }

    public static function horaCierre(string $fecha): string {
        $horario = self::obtenerHorarioDelDia($fecha);
        return $horario && $horario['hora_fin'] ? substr($horario['hora_fin'], 0, 5) : '21:00';
    }
}