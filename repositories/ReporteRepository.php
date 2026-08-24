<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

/**
 * Todo este Repository es de SOLO LECTURA (igual que DashboardRepository,
 * del cual reutilizamos un par de consultas desde el Service en vez de
 * copiarlas). Los métodos que reciben $fechaInicio/$fechaFin siguen el
 * mismo patrón que ya usas ahí.
 *
 * NOTA: no existe ninguna fuente de "satisfacción" o "calificación" de
 * dentistas en la base de datos -- por decisión del usuario, esas
 * métricas simplemente no se calculan aquí.
 */
class ReporteRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    // ============================================================
    // GENERAL
    // ============================================================

    public function contarPacientesTotales(): int {
        return (int) $this->conexion->query("SELECT COUNT(*) FROM pacientes")->fetchColumn();
    }

    public function contarPacientesNuevosEnRango(string $ini, string $fin): int {
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM pacientes p
             JOIN usuarios u ON p.usuario_id = u.id
             WHERE DATE(u.fecha_creacion) BETWEEN ? AND ?"
        );
        $stmt->execute([$ini, $fin]);
        return (int) $stmt->fetchColumn();
    }

    public function contarCitasEnRango(string $ini, string $fin, ?string $estado = null): int {
        $query = "SELECT COUNT(*) FROM citas WHERE fecha BETWEEN ? AND ?";
        $parametros = [$ini, $fin];
        if ($estado) {
            $query .= " AND estado = ?";
            $parametros[] = $estado;
        }
        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return (int) $stmt->fetchColumn();
    }

    public function calcularIngresosEnRango(string $ini, string $fin): float {
        $stmt = $this->conexion->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE fecha_pago BETWEEN ? AND ?");
        $stmt->execute([$ini, $fin]);
        return (float) $stmt->fetchColumn();
    }

    public function calcularGastosEnRango(string $ini, string $fin): float {
        $stmt = $this->conexion->prepare("SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE fecha BETWEEN ? AND ?");
        $stmt->execute([$ini, $fin]);
        return (float) $stmt->fetchColumn();
    }

    public function contarTratamientosActivos(): int {
        return (int) $this->conexion->query("SELECT COUNT(*) FROM tratamientos WHERE estado = 'activo'")->fetchColumn();
    }

    /**
     * Aproximado: no existe una columna de "fecha en que se completó"
     * el tratamiento, así que se usa fecha_fin_estimada como referencia.
     * Si más adelante agregas una fecha_completado real, este método
     * es el único que hay que tocar.
     */
    public function contarTratamientosCompletadosEnRango(string $ini, string $fin): int {
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM tratamientos WHERE estado = 'completado' AND fecha_fin_estimada BETWEEN ? AND ?"
        );
        $stmt->execute([$ini, $fin]);
        return (int) $stmt->fetchColumn();
    }

    public function obtenerCitasPorMes(int $meses): array {
        $query = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes_key, COUNT(*) AS total
                  FROM citas
                  WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) AND estado != 'cancelada'
                  GROUP BY mes_key ORDER BY mes_key ASC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$meses]);
        return $stmt->fetchAll();
    }

    public function obtenerPacientesAtendidosPorMes(int $meses): array {
        $query = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes_key, COUNT(DISTINCT paciente_id) AS total
                  FROM citas
                  WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) AND estado = 'completada'
                  GROUP BY mes_key ORDER BY mes_key ASC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$meses]);
        return $stmt->fetchAll();
    }

    // ============================================================
    // FINANCIERO
    // ============================================================

    public function obtenerMetodosPagoEnRango(string $ini, string $fin): array {
        $query = "SELECT metodo, COALESCE(SUM(monto), 0) AS total
                  FROM pagos WHERE fecha_pago BETWEEN ? AND ? GROUP BY metodo";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$ini, $fin]);
        return $stmt->fetchAll();
    }

    // ============================================================
    // PACIENTES
    // ============================================================

    /**
     * % de pacientes que tienen 2 o más citas completadas, sobre el
     * total de pacientes que tienen al menos 1 completada.
     */
    public function calcularTasaRetencion(): float {
        $query = "SELECT
                    COUNT(*) AS con_al_menos_una,
                    SUM(CASE WHEN total >= 2 THEN 1 ELSE 0 END) AS con_dos_o_mas
                  FROM (
                    SELECT paciente_id, COUNT(*) AS total
                    FROM citas WHERE estado = 'completada'
                    GROUP BY paciente_id
                  ) sub";
        $fila = $this->conexion->query($query)->fetch();

        if (!$fila || (int) $fila['con_al_menos_una'] === 0) {
            return 0.0;
        }
        return round(((int) $fila['con_dos_o_mas'] / (int) $fila['con_al_menos_una']) * 100, 1);
    }

    public function calcularVisitasPromedio(): float {
        $query = "SELECT AVG(total) FROM (
                    SELECT paciente_id, COUNT(*) AS total
                    FROM citas
                    WHERE estado = 'completada' AND YEAR(fecha) = YEAR(CURDATE())
                    GROUP BY paciente_id
                  ) sub";
        $promedio = $this->conexion->query($query)->fetchColumn();
        return $promedio ? round((float) $promedio, 1) : 0.0;
    }

    public function obtenerNuevosPacientesPorMes(int $meses): array {
        $query = "SELECT DATE_FORMAT(u.fecha_creacion, '%Y-%m') AS mes_key, COUNT(*) AS total
                  FROM pacientes p
                  JOIN usuarios u ON p.usuario_id = u.id
                  WHERE u.fecha_creacion >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                  GROUP BY mes_key ORDER BY mes_key ASC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$meses]);
        return $stmt->fetchAll();
    }

    public function obtenerDistribucionGenero(): array {
        return $this->conexion->query(
            "SELECT COALESCE(genero, 'Sin especificar') AS genero, COUNT(*) AS total FROM pacientes GROUP BY genero"
        )->fetchAll();
    }

    public function obtenerDistribucionEdad(): array {
        $query = "SELECT
                    CASE
                        WHEN edad IS NULL THEN 'Sin especificar'
                        WHEN edad <= 12 THEN '0-12 años'
                        WHEN edad <= 25 THEN '13-25 años'
                        WHEN edad <= 40 THEN '26-40 años'
                        WHEN edad <= 60 THEN '41-60 años'
                        ELSE '60+ años'
                    END AS rango,
                    COUNT(*) AS total
                  FROM (
                    SELECT TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad FROM pacientes
                  ) sub
                  GROUP BY rango";
        return $this->conexion->query($query)->fetchAll();
    }

    /** Últimos 90 días, para que el patrón semanal sea representativo pero no cargue todo el histórico. */
    public function obtenerCitasPorDiaSemana(): array {
        $query = "SELECT DAYOFWEEK(fecha) AS dia_num, COUNT(*) AS total
                  FROM citas
                  WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND estado != 'cancelada'
                  GROUP BY dia_num";
        return $this->conexion->query($query)->fetchAll();
    }

    public function obtenerCitasPorHoraDia(): array {
        $query = "SELECT HOUR(hora) AS hora_num, COUNT(*) AS total
                  FROM citas
                  WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND estado != 'cancelada'
                  GROUP BY hora_num ORDER BY hora_num ASC";
        return $this->conexion->query($query)->fetchAll();
    }

    // ============================================================
    // TRATAMIENTOS (basado en citas + su servicio, dentro de un rango)
    // ============================================================

    public function obtenerResumenPorCategoriaEnRango(string $ini, string $fin): array {
        $query = "SELECT s.categoria, COUNT(*) AS total, COALESCE(SUM(c.costo), 0) AS monto
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.estado = 'completada' AND c.fecha BETWEEN ? AND ?
                  GROUP BY s.categoria";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$ini, $fin]);
        return $stmt->fetchAll();
    }

    public function obtenerRankingServiciosEnRango(string $ini, string $fin, int $limite): array {
        $query = "SELECT s.nombre, s.categoria, COUNT(*) AS realizados, COALESCE(SUM(c.costo), 0) AS monto
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.estado = 'completada' AND c.fecha BETWEEN ? AND ?
                  GROUP BY s.id, s.nombre, s.categoria
                  ORDER BY realizados DESC
                  LIMIT ?";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindValue(1, $ini);
        $stmt->bindValue(2, $fin);
        $stmt->bindValue(3, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ============================================================
    // DENTISTAS
    // ============================================================

    public function obtenerEstadisticasPorDoctor(string $ini, string $fin): array {
        $query = "SELECT
                    d.id AS doctor_id, u.nombre, d.especialidad,
                    COUNT(c.id) AS citas_total,
                    SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) AS citas_completadas,
                    COUNT(DISTINCT CASE WHEN c.estado = 'completada' THEN c.paciente_id END) AS pacientes_atendidos,
                    (SELECT COUNT(*) FROM tratamientos t WHERE t.doctor_id = d.id AND t.estado = 'activo') AS tratamientos_activos
                  FROM doctores d
                  JOIN usuarios u ON d.usuario_id = u.id
                  LEFT JOIN citas c ON c.doctor_id = d.id AND c.fecha BETWEEN ? AND ?
                  GROUP BY d.id, u.nombre, d.especialidad
                  ORDER BY citas_completadas DESC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$ini, $fin]);
        return $stmt->fetchAll();
    }

    /**
     * Ingresos por doctor: se calcula a partir de los pagos que se
     * aplicaron a una cita o tratamiento de ese doctor (los "pagos
     * libres", sin cita/tratamiento, no se pueden atribuir a nadie).
     */
    public function obtenerIngresosPorDoctorEnRango(string $ini, string $fin): array {
        $query = "SELECT doctor_id, COALESCE(SUM(monto), 0) AS total FROM (
                    SELECT c.doctor_id, pg.monto
                    FROM pagos pg JOIN citas c ON pg.cita_id = c.id
                    WHERE pg.fecha_pago BETWEEN ? AND ?
                    UNION ALL
                    SELECT t.doctor_id, pg.monto
                    FROM pagos pg JOIN tratamientos t ON pg.tratamiento_id = t.id
                    WHERE pg.fecha_pago BETWEEN ? AND ?
                  ) sub
                  GROUP BY doctor_id";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$ini, $fin, $ini, $fin]);
        return $stmt->fetchAll();
    }

    public function obtenerTopServicioDeDoctor(int $doctorId, string $ini, string $fin): ?string {
        $query = "SELECT s.nombre
                  FROM citas c JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.doctor_id = ? AND c.estado = 'completada' AND c.fecha BETWEEN ? AND ?
                  GROUP BY s.id, s.nombre
                  ORDER BY COUNT(*) DESC
                  LIMIT 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$doctorId, $ini, $fin]);
        $resultado = $stmt->fetchColumn();
        return $resultado ?: null;
    }
}