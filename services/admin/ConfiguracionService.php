<?php

require_once __DIR__ . '/../../repositories/ConfiguracionClinicaRepository.php';
require_once __DIR__ . '/../../repositories/DoctorRepository.php';
require_once __DIR__ . '/../../repositories/NotificacionPreferenciaRepository.php';
require_once __DIR__ . '/../../repositories/LogActividadRepository.php';
require_once __DIR__ . '/../../repositories/SeguridadRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';

class ConfiguracionService {
    private ConfiguracionClinicaRepository $clinicaRepo;
    private DoctorRepository $doctorRepo;
    private NotificacionPreferenciaRepository $notifRepo;
    private LogActividadRepository $logRepo;
    private SeguridadRepository $seguridadRepo;

    public function __construct() {
        $this->clinicaRepo = new ConfiguracionClinicaRepository();
        $this->doctorRepo = new DoctorRepository();
        $this->notifRepo = new NotificacionPreferenciaRepository();
        $this->logRepo = new LogActividadRepository();
        $this->seguridadRepo = new SeguridadRepository();
    }

    // ============================================================
    // CLÍNICA
    // ============================================================

    public function obtenerClinica(): array {
        return [
            'datos' => $this->clinicaRepo->obtener(),
            'horarios' => $this->clinicaRepo->obtenerHorarios(),
        ];
    }

    public function guardarClinica(array $datos): void {
        if (empty($datos['nombre'])) {
            throw new InvalidArgumentException('El nombre de la clínica es obligatorio.');
        }
        $this->clinicaRepo->guardar($datos);
        Auditoria::registrar('configuracion', 'Actualizó los datos de la clínica', $datos['nombre']);
    }

    public function guardarHorarios(array $horarios): void {
        $this->clinicaRepo->guardarHorarios($horarios);
        Auditoria::registrar('configuracion', 'Actualizó los horarios de atención');
    }

    // ============================================================
    // USUARIOS Y ROLES (doctores)
    // ============================================================

    public function listarDoctores(): array {
        return $this->doctorRepo->listar();
    }

    public function registrarDoctor(array $datos): int {
        if (empty($datos['nombre']) || empty($datos['correo'])) {
            throw new InvalidArgumentException('Nombre y correo son obligatorios.');
        }
        if (empty($datos['contrasena']) || strlen($datos['contrasena']) < 6) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
        }
        if ($this->doctorRepo->correoExiste($datos['correo'])) {
            throw new InvalidArgumentException('Ya existe una cuenta con ese correo.');
        }
        $doctorId = $this->doctorRepo->crear($datos);
        Auditoria::registrar('configuracion', 'Agregó un doctor', $datos['nombre']);
        return $doctorId;
    }

    public function actualizarDoctor(int $doctorId, array $datos): void {
        if (empty($datos['nombre']) || empty($datos['correo'])) {
            throw new InvalidArgumentException('Nombre y correo son obligatorios.');
        }
        $doctor = $this->doctorRepo->obtenerPorId($doctorId);
        if (!$doctor) {
            throw new InvalidArgumentException('El doctor no existe.');
        }
        if ($this->doctorRepo->correoExiste($datos['correo'], $doctor['usuario_id'])) {
            throw new InvalidArgumentException('Ya existe otra cuenta con ese correo.');
        }
        $this->doctorRepo->actualizar($doctorId, $datos);
        Auditoria::registrar('configuracion', 'Editó un doctor', $datos['nombre']);
    }

    public function cambiarEstadoDoctor(int $doctorId, bool $activo): void {
        $this->doctorRepo->cambiarEstado($doctorId, $activo);
        Auditoria::registrar('configuracion', $activo ? 'Reactivó un doctor' : 'Desactivó un doctor', "ID $doctorId");
    }

    // ============================================================
    // NOTIFICACIONES (solo preferencias, sin disparo automático todavía)
    // ============================================================

    public function obtenerNotificaciones(): array {
        return [
            'eventos' => $this->notifRepo->listarEventos(),
            'alertasInternas' => $this->notifRepo->listarAlertasInternas(),
        ];
    }

    public function guardarNotificaciones(array $eventos, array $alertasInternas): void {
        $this->notifRepo->guardarEventos($eventos);
        $this->notifRepo->guardarAlertasInternas($alertasInternas);
        Auditoria::registrar('configuracion', 'Actualizó preferencias de notificaciones');
    }

    // ============================================================
    // SEGURIDAD
    // ============================================================

    public function cambiarPassword(int $usuarioId, string $actual, string $nueva): void {
        if (strlen($nueva) < 6) {
            throw new InvalidArgumentException('La nueva contraseña debe tener al menos 6 caracteres.');
        }
        $hashActual = $this->seguridadRepo->obtenerHashActual($usuarioId);
        if (!$hashActual || !password_verify($actual, $hashActual)) {
            throw new InvalidArgumentException('La contraseña actual no es correcta.');
        }
        $this->seguridadRepo->actualizarPassword($usuarioId, $nueva);
        Auditoria::registrar('configuracion', 'Cambió su contraseña');
    }

    // ============================================================
    // REGISTRO DE ACTIVIDAD
    // ============================================================

    public function obtenerActividadReciente(?string $modulo): array {
        return [
            'actividad' => $this->logRepo->listarRecientes(100, $modulo),
            'modulosDisponibles' => $this->logRepo->listarModulosDisponibles(),
        ];
    }
}