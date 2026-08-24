<?php

require_once __DIR__ . '/../../repositories/ReporteRepository.php';
require_once __DIR__ . '/../../repositories/DashboardRepository.php';
require_once __DIR__ . '/../../repositories/GastoRepository.php';

class ReporteService {
    private ReporteRepository $repo;
    private DashboardRepository $dashboardRepo;
    private GastoRepository $gastoRepo;

    public function __construct() {
        $this->repo = new ReporteRepository();
        $this->dashboardRepo = new DashboardRepository();
        $this->gastoRepo = new GastoRepository();
    }

    /**
     * Convierte "2026-03" en [primer día, último día] de ese mes.
     * Si el periodo viene vacío o mal formado, usa el mes actual.
     */
    private function periodoARango(?string $periodo): array {
        if (!$periodo || !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            $periodo = date('Y-m');
        }
        $inicio = $periodo . '-01';
        $fin = date('Y-m-t', strtotime($inicio));
        return [$inicio, $fin];
    }

    private function nombreMes(string $fechaInicio): string {
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        [$anio, $mes] = explode('-', $fechaInicio);
        return $meses[(int) $mes] . ' ' . $anio;
    }

    // ============================================================
    // GENERAL
    // ============================================================

    public function obtenerGeneral(?string $periodo): array {
        [$ini, $fin] = $this->periodoARango($periodo);

        $citasProgramadas = $this->repo->contarCitasEnRango($ini, $fin);
        $citasCompletadas = $this->repo->contarCitasEnRango($ini, $fin, 'completada');
        $citasCanceladas = $this->repo->contarCitasEnRango($ini, $fin, 'cancelada');
        $ingresos = $this->repo->calcularIngresosEnRango($ini, $fin);
        $gastos = $this->repo->calcularGastosEnRango($ini, $fin);
        $utilidad = $ingresos - $gastos;

        return [
            'periodoTexto' => $this->nombreMes($ini),
            'kpis' => [
                'pacientesTotales' => $this->repo->contarPacientesTotales(),
                'pacientesNuevos' => $this->repo->contarPacientesNuevosEnRango($ini, $fin),
                'citasCompletadas' => $citasCompletadas,
                'citasProgramadas' => $citasProgramadas,
                'citasCanceladas' => $citasCanceladas,
                'tasaAsistencia' => $citasProgramadas > 0 ? round(($citasCompletadas / $citasProgramadas) * 100, 1) : 0,
                'ingresos' => $ingresos,
                'gastos' => $gastos,
                'utilidadNeta' => $utilidad,
                'margenUtilidad' => $ingresos > 0 ? round(($utilidad / $ingresos) * 100, 1) : 0,
                'tratamientosActivos' => $this->repo->contarTratamientosActivos(),
                'tratamientosCompletados' => $this->repo->contarTratamientosCompletadosEnRango($ini, $fin),
            ],
            'citasPorMes' => $this->repo->obtenerCitasPorMes(6),
            'pacientesPorMes' => $this->repo->obtenerPacientesAtendidosPorMes(6),
        ];
    }

    // ============================================================
    // FINANCIERO
    // ============================================================

    public function obtenerFinanciero(?string $periodo): array {
        [$ini, $fin] = $this->periodoARango($periodo);

        $ingresosPorMes = $this->indexarPorMes($this->dashboardRepo->obtenerIngresosMensuales(6));
        $gastosPorMes = $this->indexarPorMes($this->gastoRepo->obtenerGastosMensuales(6));
        $meses = array_unique(array_merge(array_keys($ingresosPorMes), array_keys($gastosPorMes)));
        sort($meses);

        $detalleMensual = array_map(fn($mes) => [
            'mes' => $mes,
            'ingresos' => $ingresosPorMes[$mes] ?? 0,
            'gastos' => $gastosPorMes[$mes] ?? 0,
        ], $meses);

        $totalIngresos = array_sum(array_column($detalleMensual, 'ingresos'));
        $totalGastos = array_sum(array_column($detalleMensual, 'gastos'));

        return [
            'periodoTexto' => $this->nombreMes($ini),
            'totalIngresos6m' => $totalIngresos,
            'totalGastos6m' => $totalGastos,
            'utilidadAcumulada6m' => $totalIngresos - $totalGastos,
            'detalleMensual' => $detalleMensual,
            'metodosPago' => $this->repo->obtenerMetodosPagoEnRango($ini, $fin),
        ];
    }

    private function indexarPorMes(array $filas): array {
        $indexado = [];
        foreach ($filas as $fila) {
            $indexado[$fila['mes_key']] = (float) $fila['total'];
        }
        return $indexado;
    }

    // ============================================================
    // PACIENTES (no depende de periodo, son métricas globales)
    // ============================================================

    public function obtenerPacientes(): array {
        $edadPorRango = $this->repo->obtenerDistribucionEdad();
        $totalEdad = array_sum(array_column($edadPorRango, 'total'));
        foreach ($edadPorRango as &$fila) {
            $fila['pct'] = $totalEdad > 0 ? round(((int) $fila['total'] / $totalEdad) * 100) : 0;
        }

        return [
            'tasaRetencion' => $this->repo->calcularTasaRetencion(),
            'visitasPromedio' => $this->repo->calcularVisitasPromedio(),
            'nuevosPorMes' => $this->repo->obtenerNuevosPacientesPorMes(6),
            'distribucionGenero' => $this->repo->obtenerDistribucionGenero(),
            'distribucionEdad' => $edadPorRango,
            'citasPorDiaSemana' => $this->mapearDiasSemana($this->repo->obtenerCitasPorDiaSemana()),
            'citasPorHoraDia' => $this->repo->obtenerCitasPorHoraDia(),
        ];
    }

    // DAYOFWEEK de MySQL: 1=Domingo ... 7=Sábado. Se reordena Lun-Sáb (sin Domingo, clínica cerrada ese día).
    private function mapearDiasSemana(array $filas): array {
        $nombres = [2 => 'Lun', 3 => 'Mar', 4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb'];
        $totales = array_fill_keys(array_keys($nombres), 0);
        foreach ($filas as $fila) {
            if (isset($totales[$fila['dia_num']])) {
                $totales[$fila['dia_num']] = (int) $fila['total'];
            }
        }
        $resultado = [];
        foreach ($nombres as $num => $nombre) {
            $resultado[] = ['dia' => $nombre, 'total' => $totales[$num]];
        }
        return $resultado;
    }

    // ============================================================
    // TRATAMIENTOS
    // ============================================================

    public function obtenerTratamientos(?string $periodo): array {
        [$ini, $fin] = $this->periodoARango($periodo);

        $categorias = $this->repo->obtenerResumenPorCategoriaEnRango($ini, $fin);
        $ranking = $this->repo->obtenerRankingServiciosEnRango($ini, $fin, 8);

        return [
            'periodoTexto' => $this->nombreMes($ini),
            'totalTratamientos' => array_sum(array_column($categorias, 'total')),
            'categorias' => $categorias,
            'ranking' => $ranking,
        ];
    }

    // ============================================================
    // DENTISTAS
    // ============================================================

    public function obtenerDentistas(?string $periodo): array {
        [$ini, $fin] = $this->periodoARango($periodo);

        $porDoctor = $this->repo->obtenerEstadisticasPorDoctor($ini, $fin);
        $ingresosPorDoctor = [];
        foreach ($this->repo->obtenerIngresosPorDoctorEnRango($ini, $fin) as $fila) {
            $ingresosPorDoctor[$fila['doctor_id']] = (float) $fila['total'];
        }

        $totalIngresos = array_sum($ingresosPorDoctor);
        $totalCitas = array_sum(array_column($porDoctor, 'citas_total'));
        $totalPacientes = array_sum(array_column($porDoctor, 'pacientes_atendidos'));

        $dentistas = array_map(function ($d) use ($ingresosPorDoctor, $totalIngresos, $ini, $fin) {
            $ingresos = $ingresosPorDoctor[$d['doctor_id']] ?? 0;
            return [
                'nombre' => $d['nombre'],
                'especialidad' => $d['especialidad'],
                'citasTotal' => (int) $d['citas_total'],
                'citasCompletadas' => (int) $d['citas_completadas'],
                'pctCompletadas' => $d['citas_total'] > 0 ? round(($d['citas_completadas'] / $d['citas_total']) * 100) : 0,
                'pacientesAtendidos' => (int) $d['pacientes_atendidos'],
                'tratamientosActivos' => (int) $d['tratamientos_activos'],
                'ingresos' => $ingresos,
                'pctIngresos' => $totalIngresos > 0 ? round(($ingresos / $totalIngresos) * 100) : 0,
                'topServicio' => $this->repo->obtenerTopServicioDeDoctor((int) $d['doctor_id'], $ini, $fin),
            ];
        }, $porDoctor);

        usort($dentistas, fn($a, $b) => $b['ingresos'] <=> $a['ingresos']);

        return [
            'periodoTexto' => $this->nombreMes($ini),
            'totalCitasEquipo' => $totalCitas,
            'pacientesAtendidosEquipo' => $totalPacientes,
            'ingresosEquipo' => $totalIngresos,
            'dentistas' => $dentistas,
        ];
    }
}