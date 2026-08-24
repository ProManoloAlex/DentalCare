<?php

require_once __DIR__ . '/../../repositories/TratamientoRepository.php';

class TratamientoService {
    private TratamientoRepository $repo;

    private array $mesesAbrev = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    public function __construct() {
        $this->repo = new TratamientoRepository();
    }

    public function listarActivos(int $pacienteId): array {
        $tratamientos = $this->repo->obtenerActivos($pacienteId);

        return array_map(function ($t) {
            $costoTotal = (float) $t['costo_total'];
            $pagado = (float) $t['monto_pagado'];
            $saldo = $costoTotal - $pagado;

            return [
                'id'                    => (int) $t['id'],
                'nombre'                => $t['nombre'],
                'categoria'             => $t['categoria'],
                'descripcion'           => $t['descripcion'],
                'doctor_nombre'         => $t['doctor_nombre'],
                'sesiones_totales'      => (int) $t['sesiones_totales'],
                'sesiones_completadas'  => (int) $t['sesiones_completadas'],
                'progreso_porcentaje'   => $t['sesiones_totales'] > 0
                    ? round(($t['sesiones_completadas'] / $t['sesiones_totales']) * 100)
                    : 0,
                'fecha_inicio'          => $this->formatearFechaCorta($t['fecha_inicio']),
                'fecha_fin_estimada'    => $t['fecha_fin_estimada'] ? $this->formatearFechaCorta($t['fecha_fin_estimada']) : null,
                'costo_total'           => number_format($costoTotal, 2),
                'monto_pagado'          => number_format($pagado, 2),
                'saldo_pendiente'       => number_format($saldo, 2),
                'avance_pago_porcentaje'=> $costoTotal > 0 ? round(($pagado / $costoTotal) * 100) : 0,
                'pagado_completo'       => $saldo <= 0,
            ];
        }, $tratamientos);
    }

    public function contarActivos(int $pacienteId): int {
        return $this->repo->contarActivos($pacienteId);
    }

    public function calcularSaldoPendiente(int $pacienteId): float {
        return $this->repo->calcularSaldoPendiente($pacienteId);
    }

    public function obtenerCuenta(int $pacienteId): array {
        $detalle = $this->repo->obtenerDetalleConSaldo($pacienteId);
        $saldoTotal = 0;

        $formateado = array_map(function ($t) use (&$saldoTotal, $pacienteId) {
            $costoTotal = (float) $t['costo_total'];
            $pagado = (float) $t['monto_pagado'];
            $saldo = $costoTotal - $pagado;
            $saldoTotal += $saldo;

            return [
                'id'               => (int) $t['id'],
                'nombre'           => $t['nombre'],
                'costo_total'      => number_format($costoTotal, 2),
                'monto_pagado'     => number_format($pagado, 2),
                'saldo_pendiente'  => number_format($saldo, 2),
                'avance_porcentaje'=> $costoTotal > 0 ? round(($pagado / $costoTotal) * 100) : 0,
            ];
        }, $detalle);

        return [
            'saldo_total' => number_format($saldoTotal, 2),
            'detalle'     => $formateado,
        ];
    }

    public function obtenerHistorialPagos(int $tratamientoId, int $pacienteId): array {
        $pagos = $this->repo->obtenerPagosDeTratamiento($tratamientoId, $pacienteId);

        return array_map(fn($p) => [
            'monto'         => number_format((float) $p['monto'], 2),
            'metodo'        => ucfirst($p['metodo']),
            'fecha'         => $this->formatearFechaCorta($p['fecha_pago']),
        ], $pagos);
    }

    private function formatearFechaCorta(string $fecha): string {
        $obj = new DateTime($fecha);
        return sprintf('%02d %s %d', $obj->format('d'), $this->mesesAbrev[(int) $obj->format('n')], $obj->format('Y'));
    }
}