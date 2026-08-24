<?php

require_once __DIR__ . '/../../repositories/CuentaPacienteRepository.php';
require_once __DIR__ . '/../../repositories/SeguridadRepository.php';

class CuentaService {
    private CuentaPacienteRepository $repo;
    private SeguridadRepository $seguridadRepo;

    public function __construct() {
        $this->repo = new CuentaPacienteRepository();
        $this->seguridadRepo = new SeguridadRepository();
    }

    public function obtenerSaldo(int $pacienteId): array {
        $tratamientos = array_map(function ($t) {
            $saldo = (float) $t['costo_total'] - (float) $t['monto_pagado'];
            return [
                'tipo' => 'tratamiento',
                'id' => (int) $t['id'],
                'nombre' => $t['nombre'],
                'costoTotal' => (float) $t['costo_total'],
                'montoPagado' => (float) $t['monto_pagado'],
                'saldo' => $saldo,
            ];
        }, $this->repo->obtenerTratamientosConSaldo($pacienteId));

        $citas = array_map(function ($c) {
            return [
                'tipo' => 'cita',
                'id' => (int) $c['id'],
                'nombre' => $c['nombre'],
                'costoTotal' => (float) $c['costo'],
                'montoPagado' => 0,
                'saldo' => (float) $c['costo'],
                'fecha' => $c['fecha'],
            ];
        }, $this->repo->obtenerCitasSinPagar($pacienteId));

        return [
            'saldoTotal' => $this->repo->calcularSaldoTotal($pacienteId),
            'detalle' => array_merge($tratamientos, $citas),
        ];
    }

    public function cambiarPassword(int $usuarioId, string $actual, string $nueva): void {
        if (strlen($nueva) < 6) {
            throw new InvalidArgumentException('La nueva contraseña debe tener al menos 6 caracteres.');
        }
        $hashActual = $this->seguridadRepo->obtenerHashActual($usuarioId);
        if (!$hashActual || !password_verify($actual, $hashActual)) {
            throw new InvalidArgumentException('La contraseña actual no es correcta.');
        }
        $this->seguridadRepo->actualizarPassword($usuarioId, $nueva);
    }
}