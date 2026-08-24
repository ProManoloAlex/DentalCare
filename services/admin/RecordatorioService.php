<?php

require_once __DIR__ . '/../../repositories/ReglaRecordatorioRepository.php';
require_once __DIR__ . '/../../repositories/RecordatorioRepository.php';
require_once __DIR__ . '/../../repositories/ConfiguracionCanalesRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';
require_once __DIR__ . '/../EmailService.php';

class RecordatorioService {
    private ReglaRecordatorioRepository $reglaRepo;
    private RecordatorioRepository $recordatorioRepo;
    private ConfiguracionCanalesRepository $canalesRepo;
    private EmailService $email;

    public function __construct() {
        $this->reglaRepo = new ReglaRecordatorioRepository();
        $this->recordatorioRepo = new RecordatorioRepository();
        $this->canalesRepo = new ConfiguracionCanalesRepository();
        $this->email = new EmailService();
    }

    // ============================================================
    // RESUMEN GENERAL (KPIs de arriba)
    // ============================================================

    public function obtenerResumen(): array {
        $pendientes = $this->recordatorioRepo->obtenerPendientes();
        $historial = $this->recordatorioRepo->contarHistorial();
        $totalHistorial = array_sum($historial);

        return [
            'pendientesHoy' => count($pendientes),
            'reglasActivas' => $this->reglaRepo->contarActivas(),
            'enviados' => $historial['enviado'],
            'tasaExito' => $totalHistorial > 0 ? round(($historial['enviado'] / $totalHistorial) * 100) : 0,
        ];
    }

    // ============================================================
    // PROGRAMADOS
    // ============================================================

    public function obtenerProgramados(?string $estadoFiltro): array {
        $pendientes = array_map(function ($p) {
            return [
                'citaId' => (int) $p['cita_id'],
                'reglaId' => (int) $p['regla_id'],
                'pacienteId' => (int) $p['paciente_id'],
                'paciente' => $p['paciente_nombre'],
                'correo' => $p['paciente_correo'],
                'cita' => $p['servicio_nombre'],
                'fechaCita' => $p['cita_fecha'] . ' ' . substr($p['cita_hora'], 0, 5),
                'canal' => $p['canal'],
                'envio' => $this->textoEnvio($p['timing'], $p['horas']),
                'fechaProgramada' => $p['fecha_programada'],
                'estado' => 'pendiente',
                'mensaje' => $this->renderizarMensaje($p['mensaje'], $p['paciente_nombre'], $p['cita_fecha'], $p['cita_hora'], $p['servicio_nombre']),
            ];
        }, $this->recordatorioRepo->obtenerPendientes());

        $procesadosHoy = array_map(function ($p) {
            return [
                'citaId' => (int) $p['cita_id'],
                'reglaId' => (int) $p['regla_id'],
                'pacienteId' => (int) $p['paciente_id'],
                'paciente' => $p['paciente_nombre'],
                'correo' => $p['paciente_correo'],
                'cita' => $p['servicio_nombre'],
                'fechaCita' => $p['cita_fecha'] . ' ' . substr($p['cita_hora'], 0, 5),
                'canal' => $p['canal'],
                'envio' => null,
                'fechaProgramada' => $p['fecha_envio'],
                'estado' => $p['estado'],
                'mensaje' => null,
            ];
        }, $this->recordatorioRepo->obtenerProcesadosHoy());

        $todos = array_merge($pendientes, $procesadosHoy);

        if ($estadoFiltro) {
            $todos = array_values(array_filter($todos, fn($r) => $r['estado'] === $estadoFiltro));
        }
        return $todos;
    }

    public function obtenerStatsProgramados(): array {
        $pendientes = $this->recordatorioRepo->obtenerPendientes();
        return [
            'porEnviar' => count($pendientes),
            'enviadosHoy' => $this->recordatorioRepo->contarEnviadosHoy(),
            'fallidosHoy' => $this->recordatorioRepo->contarFallidosHoy(),
        ];
    }

    private function textoEnvio(string $timing, int $horas): string {
        return $horas . 'h ' . ($timing === 'antes' ? 'antes' : 'después');
    }

    private function renderizarMensaje(string $plantilla, string $nombre, string $fecha, string $hora, string $tratamiento): string {
        return str_replace(
            ['{{nombre}}', '{{fecha}}', '{{hora}}', '{{tratamiento}}'],
            [$nombre, $fecha, substr($hora, 0, 5), $tratamiento],
            $plantilla
        );
    }

    /**
     * Arma los datos para que el frontend construya el link mailto: y,
     * al confirmar, registre el resultado real en el historial.
     */
    public function registrarEnvio(array $datos): int {
        if (empty($datos['citaId']) || empty($datos['reglaId']) || empty($datos['pacienteId'])) {
            throw new InvalidArgumentException('Faltan datos del recordatorio.');
        }
        if (empty($datos['estado']) || !in_array($datos['estado'], ['enviado', 'fallido'], true)) {
            throw new InvalidArgumentException('Estado no válido.');
        }
        return $this->recordatorioRepo->registrarEnvio($datos);
    }
    
    /**
     * Envío real de UN recordatorio: renderiza el mensaje, lo manda por
     * SMTP vía EmailService, y registra el resultado (enviado/fallido)
     * en el historial -- reemplaza el flujo de mailto: + confirmación manual.
     */
public function enviarRecordatorio(int $citaId, int $reglaId): array {
        $canales = $this->canalesRepo->obtener();
        if (empty($canales['email_activo'])) {
            throw new InvalidArgumentException('El canal de Email está desactivado. Actívalo en Configurar Canales.');
        }

        $p = $this->recordatorioRepo->obtenerPendientePorClave($citaId, $reglaId);
        if (!$p) {
            throw new InvalidArgumentException('El recordatorio no existe o ya fue procesado.');
        }

        $mensaje = $this->renderizarMensaje($p['mensaje'], $p['paciente_nombre'], $p['cita_fecha'], $p['cita_hora'], $p['servicio_nombre']);
        $asunto = $this->prepararAsunto($canales, $p['servicio_nombre']);
        $cuerpoHtml = nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));

        $enviado = $this->email->enviar($p['paciente_correo'], $p['paciente_nombre'], $asunto, $cuerpoHtml);

        $id = $this->recordatorioRepo->registrarEnvio([
            'citaId' => $p['cita_id'], 'reglaId' => $p['regla_id'], 'pacienteId' => $p['paciente_id'],
            'canal' => 'email', 'mensaje' => $mensaje, 'fechaProgramada' => $p['fecha_programada'],
            'estado' => $enviado ? 'enviado' : 'fallido',
        ]);

        Auditoria::registrar('recordatorios', 'Envió un recordatorio', "Cita #{$p['cita_id']}, " . ($enviado ? 'enviado' : 'falló'));

        return ['ok' => $enviado, 'id' => $id];
    }

    /**
     * Envía TODOS los pendientes de un jalón (botón "Enviar todos ahora").
     * Sigue sin haber cron -- esto se dispara manualmente o cuando el
     * doctor abre la pestaña Programados, igual que ya se calculaba en vivo.
     */
public function enviarTodosPendientes(): array {
        $canales = $this->canalesRepo->obtener();
        if (empty($canales['email_activo'])) {
            return ['ok' => false, 'enviados' => 0, 'fallidos' => 0, 'mensaje' => 'El canal de Email está desactivado. Actívalo en Configurar Canales.'];
        }

        $pendientes = $this->recordatorioRepo->obtenerPendientes();
        $enviados = 0;
        $fallidos = 0;

        foreach ($pendientes as $p) {
            $mensaje = $this->renderizarMensaje($p['mensaje'], $p['paciente_nombre'], $p['cita_fecha'], $p['cita_hora'], $p['servicio_nombre']);
            $asunto = $this->prepararAsunto($canales, $p['servicio_nombre']);
            $cuerpoHtml = nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));

            $ok = $this->email->enviar($p['paciente_correo'], $p['paciente_nombre'], $asunto, $cuerpoHtml);

            $this->recordatorioRepo->registrarEnvio([
                'citaId' => $p['cita_id'], 'reglaId' => $p['regla_id'], 'pacienteId' => $p['paciente_id'],
                'canal' => 'email', 'mensaje' => $mensaje, 'fechaProgramada' => $p['fecha_programada'],
                'estado' => $ok ? 'enviado' : 'fallido',
            ]);

            $ok ? $enviados++ : $fallidos++;
        }

        Auditoria::registrar('recordatorios', 'Envió recordatorios en lote', "$enviados enviados, $fallidos fallidos");

        return ['ok' => true, 'enviados' => $enviados, 'fallidos' => $fallidos];
    }

    private function prepararAsunto(array $canales, string $servicioNombre): string {
        return !empty($canales['email_asunto'])
            ? $canales['email_asunto']
            : ('Recordatorio de tu cita — ' . $servicioNombre);
    }
    
    // ============================================================
    // REGLAS
    // ============================================================

    public function listarReglas(): array {
        return $this->reglaRepo->listar();
    }

    public function registrarRegla(array $datos): int {
        $this->validarRegla($datos);
        return $this->reglaRepo->crear($datos);
    }

    public function actualizarRegla(int $id, array $datos): void {
        $this->validarRegla($datos);
        if (!$this->reglaRepo->obtenerPorId($id)) {
            throw new InvalidArgumentException('La regla no existe.');
        }
        $this->reglaRepo->actualizar($id, $datos);
    }

    private function validarRegla(array $datos): void {
        if (empty($datos['nombre'])) {
            throw new InvalidArgumentException('El nombre de la regla es obligatorio.');
        }
        if (empty($datos['horas']) || (int) $datos['horas'] <= 0) {
            throw new InvalidArgumentException('Las horas deben ser mayor a 0.');
        }
        if (empty($datos['mensaje'])) {
            throw new InvalidArgumentException('La plantilla del mensaje es obligatoria.');
        }
        // Por ahora solo Email tiene forma de enviarse de verdad (ver
        // decisión del proyecto sobre WhatsApp) -- se bloquea crear
        // reglas que nunca se podrían disparar.
        if (($datos['canal'] ?? '') !== 'email') {
            throw new InvalidArgumentException('Por ahora solo está disponible el canal Email.');
        }
    }

    public function cambiarEstadoRegla(int $id, bool $activa): void {
        $this->reglaRepo->cambiarEstado($id, $activa);
    }

    // ============================================================
    // HISTORIAL
    // ============================================================

    public function listarHistorial(?string $busqueda, ?string $canal, ?string $estado): array {
        return $this->recordatorioRepo->listarHistorial($busqueda, $canal, $estado ? strtolower($estado) : null);
    }

    // ============================================================
    // CANALES
    // ============================================================

    public function obtenerCanales(): array {
        return $this->canalesRepo->obtener();
    }

    public function guardarCanales(array $datos): void {
        if (!empty($datos['emailActivo']) && empty($datos['emailRemitente'])) {
            throw new InvalidArgumentException('Falta el correo remitente.');
        }
        $this->canalesRepo->guardar($datos);
    }
}
