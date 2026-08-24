<?php

require_once __DIR__ . '/../../repositories/TratamientoRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';
require_once __DIR__ . '/../../repositories/CitaRepository.php';
require_once __DIR__ . '/../../config/Conexion_DB.php';
require_once __DIR__ . '/../../repositories/ConsentimientoRepository.php';

class TratamientoService {
    private TratamientoRepository $repo;
    private CitaRepository $citaRepo;
    private ConsentimientoRepository $consentimientoRepo;

    public function __construct() {
        $this->repo = new TratamientoRepository();
        $this->citaRepo = new CitaRepository();
        $this->consentimientoRepo = new ConsentimientoRepository();
    }

    public function listar(?string $busqueda, ?string $estado, string $orden): array {
        $tratamientos = $this->repo->obtenerTodosAdmin($busqueda, $estado, $orden);

        return array_map(function ($t) {
            $pagado = (float) $t['monto_pagado'];
            $costo = (float) $t['costo_total'];
            return [
                'id'                => (int) $t['id'],
                'paciente'          => $t['paciente_nombre'],
                'paciente_id'       => (int) $t['paciente_id'],
                'folio'             => 'P-' . str_pad($t['paciente_id'], 3, '0', STR_PAD_LEFT),
                'tratamiento'       => $t['nombre'],
                'categoria'         => $t['categoria'],
                'fecha'             => $t['fecha_inicio'],
                'dentista'          => $t['doctor_nombre'],
                'doctorId'          => (int) $t['doctor_id'],
                'servicioId'        => $t['servicio_id'] !== null ? (int) $t['servicio_id'] : null,
                'sesionesHechas'    => (int) $t['sesiones_completadas'],
                'sesionesTotal'     => (int) $t['sesiones_totales'],
                'estado'            => $t['estado'] === 'completado' ? 'Completado' : ($t['estado'] === 'cancelado' ? 'Cancelado' : 'En Progreso'),
                'costo'             => $costo,
                'pendiente'         => max($costo - $pagado, 0),
                'proximaCita'       => $t['proxima_cita'] ? (new DateTime($t['proxima_cita']))->format('d M Y, H:i') : null,
            ];
        }, $tratamientos);
    
        
    }

    public function obtenerResumen(): array {
        $r = $this->repo->obtenerResumenAdmin();
        return [
            'total'           => $r['total'],
            'en_progreso'     => $r['activos'],
            'completados'     => $r['completados'],
            'saldo_pendiente' => (float) $r['saldo_pendiente'],
        ];
    }

        public function asignar(array $datos): array {
        if (empty($datos['pacienteId']) || empty($datos['doctorId']) || empty($datos['nombre'])
            || empty($datos['categoria']) || empty($datos['fechaInicio']) || empty($datos['horaInicio']) || empty($datos['diagnostico'])) {
            return ['ok' => false, 'mensaje' => 'Faltan campos obligatorios.'];
        }
        if (empty($datos['servicioId'])) {
            return ['ok' => false, 'mensaje' => 'Selecciona un tratamiento del catálogo.'];
        }
        if (empty($datos['consentimientoTitulo']) || empty($datos['consentimientoTexto'])) {
            return ['ok' => false, 'mensaje' => 'Completa el consentimiento informado antes de asignar el tratamiento.'];
        }

        $conexion = Conexion::obtenConexion();
        $conexion->beginTransaction();

        try {
            $tratamientoId = $this->repo->crearAdmin($datos);

            $citaId = $this->citaRepo->crearAdmin([
                'pacienteId'    => $datos['pacienteId'],
                'doctorId'      => $datos['doctorId'],
                'servicioId'    => $datos['servicioId'],
                'tratamientoId' => $tratamientoId,
                'fecha'         => $datos['fechaInicio'],
                'hora'          => $datos['horaInicio'],
                'estado'        => 'pendiente',
                'notas'         => 'Primera sesión — creada automáticamente al asignar el tratamiento.',
            ]);

            $consentimientoId = $this->consentimientoRepo->crear([
                'pacienteId'    => $datos['pacienteId'],
                'tratamientoId' => $tratamientoId,
                'doctorId'      => $datos['doctorId'],
                'tipo'          => $datos['consentimientoTipo'] ?? 'personalizado',
                'titulo'        => $datos['consentimientoTitulo'],
                'texto'         => $datos['consentimientoTexto'],
                'fecha'         => date('Y-m-d'),
            ]);

            $conexion->commit();
        } catch (InvalidArgumentException $e) {
            $conexion->rollBack();
            return ['ok' => false, 'mensaje' => 'No se pudo agendar la primera cita: ' . $e->getMessage()];
        }

        Auditoria::registrar('tratamientos', 'Asignó un tratamiento', $datos['nombre']);
        Auditoria::registrar('citas', 'Creó una cita (automática por tratamiento)', "Cita #$citaId, {$datos['fechaInicio']} {$datos['horaInicio']}");
        Auditoria::registrar('consentimientos', 'Generó un consentimiento (automático por tratamiento)', $datos['consentimientoTitulo']);

        return ['ok' => true, 'id' => $tratamientoId, 'citaId' => $citaId, 'consentimientoId' => $consentimientoId];
    }

    public function cancelar(int $id): array {
        $this->repo->cancelarAdmin($id);
        Auditoria::registrar('tratamientos', 'Canceló un tratamiento', "Tratamiento #$id");
        return ['ok' => true];
    }
    
    public function obtenerHistorialPaciente(int $pacienteId): array {
        $tratamientos = $this->repo->obtenerHistorialPorPaciente($pacienteId);

        return array_map(function ($t) {
            $pagado = (float) $t['monto_pagado'];
            $costo = (float) $t['costo_total'];
            return [
                'id'          => (int) $t['id'],
                'tratamiento' => $t['nombre'],
                'categoria'   => $t['categoria'],
                'fecha'       => (new DateTime($t['fecha_inicio']))->format('d M Y'),
                'dentista'    => $t['doctor_nombre'],
                'sesiones'    => $t['sesiones_completadas'] . '/' . $t['sesiones_totales'],
                'estado'      => $t['estado'] === 'completado' ? 'Completado' : ($t['estado'] === 'cancelado' ? 'Cancelado' : 'En Progreso'),
                'costo'       => number_format($costo, 2),
                'pendiente'   => number_format(max($costo - $pagado, 0), 2),
            ];
        }, $tratamientos);
    }
}