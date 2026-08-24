<?php

require_once __DIR__ . '/../../repositories/CitaRepository.php';

class CitaService {
    private CitaRepository $repo;

    private array $mesesAbrev = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    public function __construct() {
        $this->repo = new CitaRepository();
    }

    public function listarProximas(int $pacienteId): array {
        $citas = $this->repo->obtenerProximas($pacienteId);

        return array_map(function ($cita) {
            $fechaObj = new DateTime($cita['fecha']);
            return [
                'id'              => (int) $cita['id'],
                'mes_abrev'       => $this->mesesAbrev[(int) $fechaObj->format('n')],
                'dia'             => (int) $fechaObj->format('j'),
                'servicio_nombre' => $cita['servicio_nombre'],
                'doctor_nombre'   => $cita['doctor_nombre'],
                'hora'            => (new DateTime($cita['hora']))->format('H:i'),
                'consultorio'     => $cita['consultorio'],
                'estado'          => $cita['estado'],
                'notas'           => $cita['notas'],
            ];
        }, $citas);
    }

    public function listarCompletadas(int $pacienteId): array {
        $citas = $this->repo->obtenerCompletadas($pacienteId);

        $doctoresUnicos = [];
        $totalPagado = 0;

        $formateadas = array_map(function ($cita) use (&$doctoresUnicos, &$totalPagado) {
            $doctoresUnicos[$cita['doctor_nombre']] = true;
            if ($cita['pagado']) {
                $totalPagado += (float) $cita['costo'];
            }

            $fechaObj = new DateTime($cita['fecha']);
            return [
                'id'                => (int) $cita['id'],
                'servicio_nombre'   => $cita['servicio_nombre'],
                'categoria'         => $cita['categoria'],
                'doctor_nombre'     => $cita['doctor_nombre'],
                'fecha_formateada'  => $fechaObj->format('d \d\e F \d\e Y'),
                'hora'              => (new DateTime($cita['hora']))->format('H:i'),
                'costo'             => number_format((float) $cita['costo'], 2),
                'pagado'            => (bool) $cita['pagado'],
                'diagnostico'       => $cita['diagnostico'],
                'indicaciones'      => $cita['indicaciones'],
            ];
        }, $citas);

        return [
            'resumen' => [
                'visitas_totales'     => count($citas),
                'doctores_atendidos'  => count($doctoresUnicos),
                'total_pagado'        => number_format($totalPagado, 2),
            ],
            'citas' => $formateadas,
        ];
    }

    public function listarHorariosDisponibles(int $doctorId, string $fecha, int $servicioId): array {
        $duracion = $this->repo->obtenerDuracionServicio($servicioId);

        if ($duracion === null) {
            return [];
        }

        return $this->repo->obtenerHorariosDisponibles($doctorId, $fecha, $duracion);
    }

    public function solicitarCita(int $pacienteId, int $doctorId, int $servicioId, string $fecha, string $hora, ?string $notas): array {
        $hoy = new DateTime('today');
        $fechaSolicitada = DateTime::createFromFormat('Y-m-d', $fecha);

        if (!$fechaSolicitada || $fechaSolicitada < $hoy) {
            return ['ok' => false, 'mensaje' => 'La fecha debe ser hoy o una fecha futura.'];
        }

        if (!DateTime::createFromFormat('H:i', $hora)) {
            return ['ok' => false, 'mensaje' => 'La hora no es válida.'];
        }

        if ($notas !== null && strlen($notas) > 500) {
            return ['ok' => false, 'mensaje' => 'Las notas no pueden superar 500 caracteres.'];
        }

        try {
            $citaId = $this->repo->crear($pacienteId, $doctorId, $servicioId, $fecha, $hora, $notas);
            return ['ok' => true, 'cita_id' => $citaId];
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public function listarNoPagadas(int $pacienteId): array {
        $citas = $this->repo->obtenerNoPagadas($pacienteId);

        return array_map(fn($c) => [
            'id'               => (int) $c['id'],
            'servicio_nombre'  => $c['servicio_nombre'],
            'fecha'            => (new DateTime($c['fecha']))->format('d/m/Y'),
            'costo'            => number_format((float) $c['costo'], 2),
        ], $citas);
    }

    public function contarProximas(int $pacienteId): int {
        return $this->repo->contarCitasProximas($pacienteId);
    }

    public function calcularSaldoPendienteBruto(int $pacienteId): float {
        return $this->repo->calcularSaldoPendienteDeCitas($pacienteId);
    }

    public function cancelarCita(int $citaId, int $pacienteId): array {
        $cancelada = $this->repo->cancelar($citaId, $pacienteId);

        if (!$cancelada) {
            return ['ok' => false, 'mensaje' => 'No se pudo cancelar (no existe o ya no está activa).'];
        }

        return ['ok' => true];
    }
}