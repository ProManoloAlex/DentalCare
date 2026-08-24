<?php

require_once __DIR__ . '/../../repositories/CitaRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';
require_once __DIR__ . '/../../repositories/TratamientoRepository.php';
require_once __DIR__ . '/../../repositories/ConsentimientoRepository.php';

class CitaService {
    private CitaRepository $repo;

    public function __construct() {
        $this->repo = new CitaRepository();
    }

    public function listarTodas(?string $busqueda, ?string $estado, ?int $doctorId): array {
        $citas = $this->repo->obtenerTodas($busqueda, $estado, $doctorId);

        return array_map(fn($c) => [
            'id'               => (int) $c['id'],
            'paciente_id'      => (int) $c['paciente_id'],
            'paciente'         => $c['paciente_nombre'],
            'telefono'         => $c['telefono'],
            'fecha'            => $c['fecha'],
            'hora'             => (new DateTime($c['hora']))->format('H:i'),
            'tratamiento'      => $c['servicio_nombre'],
            'servicio_id'      => (int) $c['servicio_id'],
            'odontologo'       => $c['doctor_nombre'],
            'doctor_id'        => (int) $c['doctor_id'],
            'duracion'         => (int) $c['duracion_min'],
            'estado'           => ucfirst($c['estado']),
            'costo'            => number_format((float) $c['costo'], 2),
            'notas'            => $c['notas'],
            'tratamientoId'    => $c['tratamiento_id'] !== null ? (int) $c['tratamiento_id'] : null,
        ], $citas);
    }

    public function obtenerParaEditar(int $citaId): array {
        $c = $this->repo->obtenerPorIdAdmin($citaId);
        if (!$c) {
            return ['ok' => false, 'mensaje' => 'Cita no encontrada.'];
        }

        return [
            'ok'          => true,
            'id'          => (int) $c['id'],
            'pacienteId'  => (int) $c['paciente_id'],
            'pacienteTexto' => $c['paciente_nombre'] . ' — ' . ($c['telefono'] ?? 'sin teléfono'),
            'doctorId'    => (int) $c['doctor_id'],
            'servicioId'  => (int) $c['servicio_id'],
            'fecha'       => $c['fecha'],
            'hora'        => (new DateTime($c['hora']))->format('H:i'),
            'estado'      => strtolower($c['estado']),
            'notas'       => $c['notas'],
        ];
    }

    public function crear(array $datos): array {
        $validacion = $this->validar($datos);
        if ($validacion !== null) {
            return $validacion;
        }

        try {
            $citaId = $this->repo->crearAdmin($datos);
            Auditoria::registrar('citas', 'Creó una cita', "Cita #$citaId, {$datos['fecha']} {$datos['hora']}");
            return ['ok' => true, 'cita_id' => $citaId];
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public function actualizar(int $citaId, array $datos): array {
        $validacion = $this->validar($datos);
        if ($validacion !== null) {
            return $validacion;
        }

        try {
            if ($datos['estado'] === 'completada') {
                $tratamientoId = $this->repo->obtenerTratamientoIdDeCita($citaId);
                if ($tratamientoId !== null) {
                    $consentimiento = (new ConsentimientoRepository())->obtenerPorTratamiento($tratamientoId);
                    if (!$consentimiento || $consentimiento['estado'] !== 'firmado') {
                        throw new InvalidArgumentException('Este tratamiento tiene un consentimiento pendiente de firma. El paciente debe firmarlo antes de poder completar esta cita.');
                    }
                }
            }

            $this->repo->actualizarAdmin($citaId, $datos);
            Auditoria::registrar('citas', 'Editó una cita', "Cita #$citaId");

            if ($datos['estado'] === 'completada') {
                $tratamientoId = $this->repo->obtenerTratamientoIdDeCita($citaId);
                if ($tratamientoId !== null) {
                    (new TratamientoRepository())->sumarSesionCompletada($tratamientoId);
                    Auditoria::registrar('tratamientos', 'Sumó una sesión completada', "Tratamiento #$tratamientoId (vía cita #$citaId)");
                }
            }

            return ['ok' => true];
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public function cancelar(int $citaId): array {
        $this->repo->cancelarAdmin($citaId);
        Auditoria::registrar('citas', 'Canceló una cita', "Cita #$citaId");
        return ['ok' => true];
    }

    private function validar(array $datos): ?array {
        if (empty($datos['pacienteId']) || empty($datos['doctorId']) || empty($datos['servicioId'])
            || empty($datos['fecha']) || empty($datos['hora']) || empty($datos['estado'])) {
            return ['ok' => false, 'mensaje' => 'Faltan campos obligatorios.'];
        }

        $estadosValidos = ['pendiente', 'confirmada', 'completada', 'cancelada'];
        if (!in_array($datos['estado'], $estadosValidos, true)) {
            return ['ok' => false, 'mensaje' => 'Estado no válido.'];
        }

        return null;
    }
}