<?php

require_once __DIR__ . '/../../repositories/ConsentimientoRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';
require_once __DIR__ . '/../../repositories/TratamientoRepository.php';
require_once __DIR__ . '/../../repositories/CitaRepository.php';

class ConsentimientoService {
    private ConsentimientoRepository $repo;

    // Única fuente de verdad de las plantillas -- antes vivían duplicadas
    // en el JS del mock. Guardar el texto en la tabla al generar el
    // consentimiento (ver crear()) hace que, aunque cambies estas plantillas
    // después, los documentos ya firmados no se alteren retroactivamente.
    private const TIPOS = [
        'extraccion' => [
            'titulo' => 'Extracción Dental',
            'desc' => 'Consentimiento para extracción simple o quirúrgica',
            'icon' => 'bi-scissors',
            'texto' => "CONSENTIMIENTO INFORMADO PARA EXTRACCIÓN DENTAL\n\nEl/la paciente declara haber sido informado/a sobre el procedimiento de extracción dental, incluyendo:\n\n1. DESCRIPCIÓN DEL PROCEDIMIENTO: La extracción dental consiste en la remoción del diente de su alvéolo mediante instrumentos específicos, bajo anestesia local.\n\n2. RIESGOS Y COMPLICACIONES POSIBLES: Dolor, inflamación, sangrado, infección, y en casos poco frecuentes, daño a estructuras vecinas.\n\n3. ALTERNATIVAS DE TRATAMIENTO: Se han explicado alternativas cuando aplican.\n\nEl/la paciente firma en señal de haber comprendido la información anterior.",
        ],
        'endodoncia' => [
            'titulo' => 'Endodoncia',
            'desc' => 'Tratamiento de conductos radiculares',
            'icon' => 'bi-shield-lock',
            'texto' => "CONSENTIMIENTO INFORMADO PARA ENDODONCIA\n\nEl/la paciente declara haber sido informado/a sobre el tratamiento de conductos radiculares, incluyendo:\n\n1. DESCRIPCIÓN DEL PROCEDIMIENTO: Limpieza, desinfección y sellado del sistema de conductos del diente afectado.\n\n2. RIESGOS Y COMPLICACIONES POSIBLES: Dolor posterior al tratamiento, posible fractura de instrumental, necesidad de retratamiento.\n\n3. ALTERNATIVAS: Extracción del diente como alternativa.\n\nEl/la paciente firma en señal de haber comprendido la información anterior.",
        ],
        'implante' => [
            'titulo' => 'Implante Dental',
            'desc' => 'Colocación de implante osteointegrado',
            'icon' => 'bi-magnet',
            'texto' => "CONSENTIMIENTO INFORMADO PARA IMPLANTE DENTAL\n\nEl/la paciente declara haber sido informado/a sobre la colocación de implante dental, incluyendo:\n\n1. DESCRIPCIÓN DEL PROCEDIMIENTO: Colocación quirúrgica de un implante de titanio en el hueso maxilar/mandibular.\n\n2. RIESGOS Y COMPLICACIONES POSIBLES: Infección, rechazo del implante, daño a estructuras vecinas, fracaso de osteointegración.\n\n3. CUIDADOS POSTERIORES: Se han explicado los cuidados necesarios tras la cirugía.\n\nEl/la paciente firma en señal de haber comprendido la información anterior.",
        ],
        'ortodoncia' => [
            'titulo' => 'Ortodoncia',
            'desc' => 'Tratamiento con brackets o alineadores',
            'icon' => 'bi-diagram-3',
            'texto' => "CONSENTIMIENTO INFORMADO PARA ORTODONCIA\n\nEl/la paciente declara haber sido informado/a sobre el tratamiento de ortodoncia, incluyendo:\n\n1. DESCRIPCIÓN DEL PROCEDIMIENTO: Uso de brackets o alineadores para corregir la posición dental a lo largo de un tratamiento prolongado.\n\n2. RIESGOS Y COMPLICACIONES POSIBLES: Molestia inicial, reabsorción radicular leve, necesidad de cooperación del paciente para el éxito del tratamiento.\n\n3. DURACIÓN ESTIMADA: Variable según el caso clínico.\n\nEl/la paciente firma en señal de haber comprendido la información anterior.",
        ],
        'blanqueamiento' => [
            'titulo' => 'Blanqueamiento Dental',
            'desc' => 'Aclaramiento estético del esmalte',
            'icon' => 'bi-stars',
            'texto' => "CONSENTIMIENTO INFORMADO PARA BLANQUEAMIENTO DENTAL\n\nEl/la paciente declara haber sido informado/a sobre el tratamiento de blanqueamiento dental, incluyendo:\n\n1. DESCRIPCIÓN DEL PROCEDIMIENTO: Aplicación de agente blanqueador sobre la superficie dental.\n\n2. RIESGOS Y COMPLICACIONES POSIBLES: Sensibilidad dental temporal, irritación gingival leve.\n\n3. RESULTADOS: El resultado puede variar según el caso y no es permanente.\n\nEl/la paciente firma en señal de haber comprendido la información anterior.",
        ],
        'periodontal' => [
            'titulo' => 'Cirugía Periodontal',
            'desc' => 'Intervención quirúrgica de tejidos periodontales',
            'icon' => 'bi-scissors',
            'texto' => "CONSENTIMIENTO INFORMADO PARA CIRUGÍA PERIODONTAL\n\nEl/la paciente declara haber sido informado/a sobre la intervención quirúrgica periodontal, incluyendo:\n\n1. DESCRIPCIÓN DEL PROCEDIMIENTO: Intervención sobre encía y tejidos de soporte para tratar enfermedad periodontal.\n\n2. RIESGOS Y COMPLICACIONES POSIBLES: Sangrado, inflamación, sensibilidad dental, recesión gingival.\n\n3. CUIDADOS POSTERIORES: Se han explicado las indicaciones postoperatorias.\n\nEl/la paciente firma en señal de haber comprendido la información anterior.",
        ],
    ];

    public function __construct() {
        $this->repo = new ConsentimientoRepository();
    }

    public function obtenerTipos(): array {
        return self::TIPOS;
    }

    public function listar(?string $busqueda, ?string $estado): array {
        return $this->repo->listar($busqueda, $estado ? strtolower($estado) : null);
    }

    public function obtenerResumen(): array {
        $porEstado = $this->repo->contarPorEstado();
        return [
            'total' => array_sum($porEstado),
            'pendientes' => $porEstado['pendiente'],
            'firmados' => $porEstado['firmado'],
            'rechazados' => $porEstado['rechazado'],
        ];
    }

    public function generar(array $datos): int {
        if (empty($datos['pacienteId'])) {
            throw new InvalidArgumentException('Selecciona un paciente.');
        }

        if (!empty($datos['titulo']) && !empty($datos['texto'])) {
            // Camino nuevo: el texto ya viene armado por el doctor (desde el
            // modal "Asignar Tratamiento", editado libremente) -- no depende
            // de que el tipo exista en la lista fija de TIPOS.
            $titulo = trim($datos['titulo']);
            $texto = trim($datos['texto']);
            $tipo = $datos['tipo'] ?? 'personalizado';
        } else {
            // Camino viejo: el modal "Nuevo Consentimiento" del admin, que
            // sigue mandando solo un 'tipo' de la lista fija.
            if (empty($datos['tipo']) || !isset(self::TIPOS[$datos['tipo']])) {
                throw new InvalidArgumentException('Selecciona un tipo de consentimiento válido.');
            }
            $plantilla = self::TIPOS[$datos['tipo']];
            $titulo = $plantilla['titulo'];
            $texto = $plantilla['texto'];
            $tipo = $datos['tipo'];
        }

        $consentimientoId = $this->repo->crear([
            'pacienteId'    => $datos['pacienteId'],
            'tratamientoId' => $datos['tratamientoId'] ?? null,
            'doctorId'      => $datos['doctorId'],
            'tipo'          => $tipo,
            'titulo'        => $titulo,
            'texto'         => $texto,
            'fecha'         => date('Y-m-d'),
        ]);
        Auditoria::registrar('consentimientos', 'Generó un consentimiento', $titulo);
        return $consentimientoId;
    }

    /**
     * $nuevoEstado: 'firmado' o 'rechazado'. $firmadoPor: 'doctor' por
     * ahora -- cuando se agregue la firma desde el portal del paciente,
     * ese endpoint llama este mismo método con $firmadoPor = 'paciente'.
     */
    public function firmar(int $id, string $nuevoEstado, ?string $firma, string $firmadoPor = 'doctor'): void {
        if (!in_array($nuevoEstado, ['firmado', 'rechazado'], true)) {
            throw new InvalidArgumentException('Estado no válido.');
        }
        if ($nuevoEstado === 'firmado' && empty($firma)) {
            throw new InvalidArgumentException('Falta el nombre de la firma.');
        }

        $consentimiento = $this->repo->obtenerPorId($id);
        if (!$consentimiento) {
            throw new InvalidArgumentException('El consentimiento no existe.');
        }
        if ($consentimiento['estado'] !== 'pendiente') {
            throw new InvalidArgumentException('Este consentimiento ya fue procesado.');
        }

        $this->repo->firmar($id, $nuevoEstado, $firma, $firmadoPor);
        Auditoria::registrar('consentimientos', $nuevoEstado === 'firmado' ? 'Firmó un consentimiento' : 'Rechazó un consentimiento', "Consentimiento #$id");

        // Si el consentimiento pertenece a un tratamiento y se rechaza, ya no
        // tiene sentido dejarlo "en progreso" -- se cancela solo, junto con
        // cualquier cita pendiente/confirmada que tuviera agendada.
        if ($nuevoEstado === 'rechazado' && !empty($consentimiento['tratamiento_id'])) {
            $tratamientoId = (int) $consentimiento['tratamiento_id'];
            (new TratamientoRepository())->cancelarAdmin($tratamientoId);
            (new CitaRepository())->cancelarPorTratamiento($tratamientoId);
            Auditoria::registrar('tratamientos', 'Canceló un tratamiento (consentimiento rechazado)', "Tratamiento #$tratamientoId");
        }
    }

    public function obtenerDetalle(int $id): ?array {
        return $this->repo->obtenerPorId($id);
    }

    // ============================================================
    // PORTAL DEL PACIENTE
    // ============================================================

    public function listarParaPaciente(int $pacienteId): array {
        return $this->repo->listarPorPaciente($pacienteId);
    }

    /**
     * Firma/rechaza desde el portal del paciente. A diferencia de
     * firmar() (que usa el doctor autenticado y confía en el id que
     * manda), aquí SIEMPRE se valida que el consentimiento pertenezca
     * al paciente en sesión -- si no, se rechaza como si no existiera,
     * para no revelar si el id pertenece a otro paciente.
     */
    public function firmarComoPaciente(int $id, int $pacienteId, string $nuevoEstado, ?string $firma): void {
        $consentimiento = $this->repo->obtenerPorId($id);
        if (!$consentimiento || (int) $consentimiento['paciente_id'] !== $pacienteId) {
            throw new InvalidArgumentException('El consentimiento no existe.');
        }
        $this->firmar($id, $nuevoEstado, $firma, 'paciente');
    }
}