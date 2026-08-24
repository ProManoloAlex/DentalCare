<?php

require_once __DIR__ . '/../../repositories/PacienteRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';

class PacienteService {
    private PacienteRepository $repo;

    public function __construct() {
        $this->repo = new PacienteRepository();
    }

    public function listar(?string $busqueda, ?string $estado, string $orden): array {
        $pacientes = $this->repo->obtenerListado($busqueda, $estado, $orden);

        return array_map(function ($p) {
            return [
                'id'             => (int) $p['paciente_id'],
                'folio'          => 'P-' . str_pad($p['paciente_id'], 3, '0', STR_PAD_LEFT),
                'nombre'         => $p['nombre'],
                'correo'         => $p['correo'],
                'telefono'       => $p['telefono'],
                'edad'           => $this->calcularEdad($p['fecha_nacimiento']),
                'ultima_visita'  => $p['ultima_visita'] ? (new DateTime($p['ultima_visita']))->format('d M Y') : 'Sin registro',
                'proxima_cita'   => $p['proxima_cita'] ? (new DateTime($p['proxima_cita']))->format('d M Y') : 'Sin cita',
                'visitas'        => (int) $p['visitas'],
                'saldo'          => number_format((float) $p['saldo'], 2),
                'tiene_saldo'    => (float) $p['saldo'] > 0,
                'activo'         => (bool) $p['activo'],
            ];
        }, $pacientes);
    }

    public function obtenerResumen(): array {
        return $this->repo->obtenerResumen();
    }

    public function crear(array $datos, ?string $contrasenaAsignada = null): array {
        if ($this->repo->correoExiste($datos['email'])) {
            return ['ok' => false, 'mensaje' => 'Ya existe un paciente (o usuario) con ese correo.'];
        }

        if (empty($datos['nombre']) || empty($datos['email']) || empty($datos['telefono']) || empty($datos['fechaNacimiento'])) {
            return ['ok' => false, 'mensaje' => 'Faltan campos obligatorios.'];
        }

        if ($contrasenaAsignada !== null && strlen($contrasenaAsignada) < 6) {
            return ['ok' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres.'];
        }

        $pacienteId = $this->repo->crear($datos, $contrasenaAsignada);
        Auditoria::registrar('pacientes', 'Registró un paciente', $datos['nombre']);

        return ['ok' => true, 'paciente_id' => $pacienteId];
    }

    public function otorgarAcceso(int $pacienteId, string $contrasena): array {
        if (strlen($contrasena) < 6) {
            return ['ok' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres.'];
        }

        $p = $this->repo->obtenerDetalle($pacienteId);
        if (!$p) {
            return ['ok' => false, 'mensaje' => 'Paciente no encontrado.'];
        }

        $this->repo->otorgarAcceso($pacienteId, $contrasena);
        Auditoria::registrar('pacientes', 'Otorgó acceso al portal', "Paciente #$pacienteId — " . $p['nombre']);
        return ['ok' => true];
    }

    public function obtenerDetalle(int $pacienteId): array {
        $p = $this->repo->obtenerDetalle($pacienteId);

        if (!$p) {
            return ['ok' => false, 'mensaje' => 'Paciente no encontrado.'];
        }

        return [
            'ok'                 => true,
            'id'                 => (int) $p['paciente_id'],
            'folio'              => 'P-' . str_pad($p['paciente_id'], 3, '0', STR_PAD_LEFT),
            'nombre'             => $p['nombre'],
            'correo'             => $p['correo'],
            'telefono'           => $p['telefono'],
            'activo'             => (bool) $p['activo'],
            'tiene_acceso'       => (bool) $p['tiene_acceso'],
            'edad'               => $this->calcularEdad($p['fecha_nacimiento']),
            'fecha_nacimiento_larga'  => $p['fecha_nacimiento'] ? $this->formatearFechaLarga($p['fecha_nacimiento']) : '—',
            'fecha_nacimiento_input'  => $p['fecha_nacimiento'] ?? '',
            'genero'             => $p['genero'] ?? '—',
            'genero_valor'       => $p['genero'] ?? '',
            'tipo_sangre'        => $p['tipo_sangre'] ?? '—',
            'tipo_sangre_valor'  => $p['tipo_sangre'] ?? '',
            'direccion'          => $p['direccion'] ?? '—',
            'alergias'           => $p['alergias'] ?? '—',
            'contacto_emergencia'=> $p['contacto_emergencia'] ?? '—',
            'visitas'            => (int) $p['visitas'],
            'ultima_visita'      => $p['ultima_visita'] ? (new DateTime($p['ultima_visita']))->format('d M Y') : 'Sin registro',
            'proxima_cita'       => $p['proxima_cita'] ? (new DateTime($p['proxima_cita']))->format('d M Y') : 'Sin cita',
            'saldo'              => number_format((float) $p['saldo'], 2),
        ];
    }

    public function actualizar(int $pacienteId, array $datos): array {
        $p = $this->repo->obtenerDetalle($pacienteId);
        if (!$p) {
            return ['ok' => false, 'mensaje' => 'Paciente no encontrado.'];
        }

        if (empty($datos['nombre']) || empty($datos['email']) || empty($datos['telefono']) || empty($datos['fechaNacimiento'])) {
            return ['ok' => false, 'mensaje' => 'Faltan campos obligatorios.'];
        }

        if ($this->repo->correoExisteExcluyendo($datos['email'], (int) $p['usuario_id'])) {
            return ['ok' => false, 'mensaje' => 'Ese correo ya lo usa otro usuario.'];
        }

        $this->repo->actualizar($pacienteId, $datos);
        Auditoria::registrar('pacientes', 'Editó un paciente', $datos['nombre']);
        return ['ok' => true];
    }

    public function cambiarEstado(int $pacienteId, bool $activo): array {
        $this->repo->cambiarEstado($pacienteId, $activo);
        Auditoria::registrar('pacientes', $activo ? 'Reactivó un paciente' : 'Desactivó un paciente', "Paciente #$pacienteId");
        return ['ok' => true];
    }

    private function formatearFechaLarga(string $fecha): string {
        $meses = [
            1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
            7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
        ];
        $obj = new DateTime($fecha);
        return sprintf('%d de %s de %d', (int) $obj->format('j'), $meses[(int) $obj->format('n')], (int) $obj->format('Y'));
    }

    private function calcularEdad(?string $fechaNacimiento): string {
        if (!$fechaNacimiento) return '—';
        $nacimiento = new DateTime($fechaNacimiento);
        $hoy = new DateTime();
        return $nacimiento->diff($hoy)->y . ' años';
    }
    
    public function obtenerFacturacion(int $pacienteId): array {
        $p = $this->repo->obtenerDetalle($pacienteId);
        if (!$p) {
            return ['ok' => false, 'mensaje' => 'Paciente no encontrado.'];
        }

        $totalPagado = 0;
        $saldoPendiente = 0;
        $totalFacturado = 0;

        $citas = array_map(function ($c) use (&$totalPagado, &$saldoPendiente, &$totalFacturado) {
            $costo = (float) $c['costo'];
            $esPagado = (bool) $c['pagado'];
            $esCancelada = $c['estado'] === 'cancelada';

            if (!$esCancelada) {
                $totalFacturado += $costo;
                $esPagado ? $totalPagado += $costo : $saldoPendiente += $costo;
            }

            return [
                'fecha' => (new DateTime($c['fecha']))->format('d M Y'),
                'servicio' => $c['servicio'],
                'monto' => number_format($costo, 2),
                'estado_pago' => $esCancelada ? 'Cancelada' : ($esPagado ? 'Pagado' : 'Pendiente'),
                'pagado' => $esPagado,
            ];
        }, $this->repo->obtenerHistorialCitas($pacienteId));

        return [
            'ok' => true,
            'citas' => $citas,
            'resumen' => [
                'total_pagado' => number_format($totalPagado, 2),
                'saldo_pendiente' => number_format($saldoPendiente, 2),
                'total_facturado' => number_format($totalFacturado, 2),
            ],
        ];
    }
}