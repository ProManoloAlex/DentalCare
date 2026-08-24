<?php

require_once __DIR__ . '/../../repositories/DashboardRepository.php';

class DashboardService {
    private DashboardRepository $repo;

    private array $mesesAbrev = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    public function __construct() {
        $this->repo = new DashboardRepository();
    }

    public function obtenerResumenCompleto(string $periodo = 'hoy'): array {
        [$fechaInicio, $fechaFin] = $this->calcularRango($periodo);

        return [
            'periodo' => $periodo,
            'resumen' => [
                'citas'             => $this->repo->contarCitasEnRango($fechaInicio, $fechaFin),
                'pacientesActivos'  => $this->repo->contarPacientesActivos(),
                'ingresos'          => $this->repo->calcularIngresosEnRango($fechaInicio, $fechaFin),
                'citasPendientes'   => $this->repo->contarCitasPendientes(),
            ],
            'ingresosMensuales' => $this->formatearIngresosMensuales($this->repo->obtenerIngresosMensuales(6)),
            'citas'             => $this->formatearCitasHoy($this->repo->obtenerCitasEnRango($fechaInicio, $fechaFin)),
            'pagosRecientes'    => $this->formatearPagosRecientes($this->repo->obtenerPagosRecientes(3)),
            'tratamientosTop'   => $this->formatearTratamientosTop($this->repo->obtenerServiciosMasSolicitados(3)),
        ];
    }

    private function calcularRango(string $periodo): array {
        $hoy = new DateTime('today');

        switch ($periodo) {
            case 'semana':
                $inicio = (clone $hoy)->modify('monday this week');
                $fin = (clone $hoy)->modify('sunday this week');
                break;
            case 'mes':
                $inicio = (clone $hoy)->modify('first day of this month');
                $fin = (clone $hoy)->modify('last day of this month');
                break;
            default: // 'hoy'
                $inicio = $hoy;
                $fin = $hoy;
        }

        return [$inicio->format('Y-m-d'), $fin->format('Y-m-d')];
    }

    private function formatearIngresosMensuales(array $filas): array {
        return array_map(function ($f) {
            [$anio, $mes] = explode('-', $f['mes_key']);
            return [
                'mes'   => $this->mesesAbrev[(int) $mes],
                'total' => (float) $f['total'],
            ];
        }, $filas);
    }

    private function formatearCitasHoy(array $filas): array {
        return array_map(fn($c) => [
            'paciente'    => $c['paciente_nombre'],
            'tratamiento' => $c['servicio_nombre'],
            'doctor'      => $c['doctor_nombre'],
            'hora'        => (new DateTime($c['hora']))->format('h:i A'),
            'estado'      => ucfirst($c['estado']),
        ], $filas);
    }

    private function formatearPagosRecientes(array $filas): array {
        $hoy = (new DateTime())->format('Y-m-d');
        $ayer = (new DateTime('yesterday'))->format('Y-m-d');

        return array_map(function ($p) use ($hoy, $ayer) {
            $fecha = $p['fecha_pago'];
            $fechaTexto = match (true) {
                $fecha === $hoy  => 'Hoy',
                $fecha === $ayer => 'Ayer',
                default          => (new DateTime($fecha))->format('d/m/Y'),
            };

            return [
                'paciente' => $p['paciente_nombre'],
                'concepto' => $p['tratamiento_nombre'] ?? 'Pago general',
                'fecha'    => $fechaTexto,
                'monto'    => (float) $p['monto'],
                'metodo'   => ucfirst($p['metodo']),
            ];
        }, $filas);
    }

    private function formatearTratamientosTop(array $filas): array {
        return array_map(fn($f) => [
            'nombre' => $f['nombre'],
            'total'  => (int) $f['total'],
        ], $filas);
    }
}