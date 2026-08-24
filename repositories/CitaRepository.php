<?php

require_once __DIR__ . '/../config/Conexion_DB.php';
require_once __DIR__ . '/../config/HorarioClinica.php';

class CitaRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function obtenerProximas(int $pacienteId): array {
        $query = "SELECT 
                    c.id, c.fecha, c.hora, c.consultorio, c.estado, c.notas, c.costo,
                    s.nombre AS servicio_nombre,
                    u.nombre AS doctor_nombre
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  JOIN doctores d  ON c.doctor_id = d.id
                  JOIN usuarios u  ON d.usuario_id = u.id
                  WHERE c.paciente_id = ?
                    AND c.estado IN ('pendiente', 'confirmada')
                    AND c.fecha >= CURDATE()
                  ORDER BY c.fecha ASC, c.hora ASC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        return $stmt->fetchAll();
    }

    public function obtenerCompletadas(int $pacienteId): array {
        $query = "SELECT 
                    c.id, c.fecha, c.hora, c.costo, c.pagado, c.diagnostico, c.indicaciones,
                    s.nombre AS servicio_nombre, s.categoria,
                    u.nombre AS doctor_nombre
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  JOIN doctores d  ON c.doctor_id = d.id
                  JOIN usuarios u  ON d.usuario_id = u.id
                  WHERE c.paciente_id = ?
                    AND c.estado = 'completada'
                  ORDER BY c.fecha DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        return $stmt->fetchAll();
    }

    public function contarCitasProximas(int $pacienteId): int {
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM citas 
             WHERE paciente_id = ? AND estado IN ('pendiente','confirmada') AND fecha >= CURDATE()"
        );
        $stmt->execute([$pacienteId]);
        return (int) $stmt->fetchColumn();
    }

    public function obtenerNoPagadas(int $pacienteId): array {
        $query = "SELECT 
                    c.id, c.fecha, c.costo,
                    s.nombre AS servicio_nombre
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.paciente_id = ? AND c.pagado = 0 AND c.estado != 'cancelada'
                  ORDER BY c.fecha DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        return $stmt->fetchAll();
    }

    public function calcularSaldoPendienteDeCitas(int $pacienteId): float {
        // Nota: esto es solo el saldo que viene de citas sueltas no pagadas.
        // Cuando exista la tabla "tratamientos", este saldo se sumará con
        // el pendiente de ahí (ver services/pacientes/CitaService::obtenerDashboard).
        $stmt = $this->conexion->prepare(
            "SELECT COALESCE(SUM(costo), 0) FROM citas 
             WHERE paciente_id = ? AND pagado = 0 AND estado != 'cancelada'"
        );
        $stmt->execute([$pacienteId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Calcula qué horas están libres para un doctor en una fecha dada,
     * tomando en cuenta: horario de apertura/cierre, hora de comida,
     * citas ya existentes de ese doctor ese día, y que no sea una
     * hora que ya pasó (si la fecha es hoy).
     */
    public function obtenerHorariosDisponibles(int $doctorId, string $fecha, int $duracionMin, ?int $excluirCitaId = null): array {
        if (!HorarioClinica::estaAbierto($fecha)) {
            return [];
        }

        $ocupados = $this->obtenerIntervalosOcupados($doctorId, $fecha, $excluirCitaId);

        $inicioJornada = new DateTime("$fecha " . HorarioClinica::horaApertura($fecha));
        $finJornada    = new DateTime("$fecha " . HorarioClinica::horaCierre($fecha));
        $comidaInicio  = new DateTime("$fecha " . HorarioClinica::COMIDA_INICIO);
        $comidaFin     = new DateTime("$fecha " . HorarioClinica::COMIDA_FIN);
        $ahora         = new DateTime();

        $slots = [];
        $cursor = clone $inicioJornada;

        while (true) {
            $finSlot = (clone $cursor)->modify("+{$duracionMin} minutes");
            if ($finSlot > $finJornada) break;

            $solapaComida   = $cursor < $comidaFin && $finSlot > $comidaInicio;
            $yaPaso         = $cursor < $ahora;
            $solapaOcupado  = false;

            foreach ($ocupados as $o) {
                if ($cursor < $o['fin'] && $finSlot > $o['inicio']) {
                    $solapaOcupado = true;
                    break;
                }
            }

            if (!$solapaComida && !$yaPaso && !$solapaOcupado) {
                $slots[] = $cursor->format('H:i');
            }

            // El siguiente horario ofrecido empieza justo donde termina
            // este bloque, así cada opción es un espacio autocontenido
            // del tamaño exacto del servicio (nada de huecos que se encimen).
            $cursor->modify("+{$duracionMin} minutes");
        }

        return $slots;
    }

    /**
     * Vuelve a verificar disponibilidad justo antes de guardar,
     * por si dos pacientes seleccionaron el mismo horario casi
     * al mismo tiempo (el navegador ya validó, pero nunca hay
     * que confiar solo en eso).
     */
    public function estaDisponible(int $doctorId, string $fecha, string $hora, int $duracionMin, ?int $excluirCitaId = null): bool {
        return $this->obtenerRazonNoDisponible($doctorId, $fecha, $hora, $duracionMin, $excluirCitaId) === null;
    }

    /**
     * Igual que estaDisponible(), pero devuelve el motivo específico
     * (o null si sí está libre) — así el mensaje de error que ve el
     * doctor dice la verdad exacta, en vez de "ocupado" para todo.
     */
    public function obtenerRazonNoDisponible(int $doctorId, string $fecha, string $hora, int $duracionMin, ?int $excluirCitaId = null): ?string {
        if (!HorarioClinica::estaAbierto($fecha)) {
            return 'La clínica no abre ese día (revisa Configuración → Clínica → Horarios).';
        }

        $inicio = new DateTime("$fecha $hora");
        $fin = (clone $inicio)->modify("+{$duracionMin} minutes");

        $aperturaJornada = new DateTime("$fecha " . HorarioClinica::horaApertura($fecha));
        $cierreJornada   = new DateTime("$fecha " . HorarioClinica::horaCierre($fecha));
        if ($inicio < $aperturaJornada || $fin > $cierreJornada) {
            return sprintf(
                'Esa hora está fuera del horario de la clínica ese día (%s a %s).',
                HorarioClinica::horaApertura($fecha), HorarioClinica::horaCierre($fecha)
            );
        }

        $comidaInicio = new DateTime("$fecha " . HorarioClinica::COMIDA_INICIO);
        $comidaFin    = new DateTime("$fecha " . HorarioClinica::COMIDA_FIN);
        if ($inicio < $comidaFin && $fin > $comidaInicio) {
            return sprintf(
                'Esa hora cae dentro del horario de comida (%s a %s).',
                HorarioClinica::COMIDA_INICIO, HorarioClinica::COMIDA_FIN
            );
        }

        foreach ($this->obtenerIntervalosOcupados($doctorId, $fecha, $excluirCitaId) as $o) {
            if ($inicio < $o['fin'] && $fin > $o['inicio']) {
                return 'Ese doctor ya tiene otra cita que se encima con ese horario.';
            }
        }

        return null;
    }

    private function obtenerIntervalosOcupados(int $doctorId, string $fecha, ?int $excluirCitaId = null): array {
        $query = "SELECT c.hora, s.duracion_min
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.doctor_id = ? AND c.fecha = ? 
                    AND c.estado IN ('pendiente', 'confirmada')";
        $params = [$doctorId, $fecha];

        if ($excluirCitaId !== null) {
            $query .= " AND c.id != ?";
            $params[] = $excluirCitaId;
        }

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($params);

        return array_map(function ($fila) use ($fecha) {
            $inicio = new DateTime("$fecha {$fila['hora']}");
            $fin = (clone $inicio)->modify("+{$fila['duracion_min']} minutes");
            return ['inicio' => $inicio, 'fin' => $fin];
        }, $stmt->fetchAll());
    }

    public function obtenerDuracionServicio(int $servicioId): ?int {
        $stmt = $this->conexion->prepare("SELECT duracion_min FROM servicios WHERE id = ? AND activo = 1");
        $stmt->execute([$servicioId]);
        $resultado = $stmt->fetchColumn();
        return $resultado !== false ? (int) $resultado : null;
    }

    public function crear(int $pacienteId, int $doctorId, int $servicioId, string $fecha, string $hora, ?string $notas): int {
        // El costo y consultorio se toman del catálogo/doctor en el servidor,
        // nunca se confía en un precio que venga del cliente.
        $stmtServicio = $this->conexion->prepare("SELECT precio, duracion_min FROM servicios WHERE id = ? AND activo = 1");
        $stmtServicio->execute([$servicioId]);
        $servicio = $stmtServicio->fetch();

        if (!$servicio) {
            throw new InvalidArgumentException('Servicio no válido.');
        }

        $stmtDoctor = $this->conexion->prepare("SELECT consultorio FROM doctores WHERE id = ?");
        $stmtDoctor->execute([$doctorId]);
        $doctor = $stmtDoctor->fetch();

        if (!$doctor) {
            throw new InvalidArgumentException('Doctor no válido.');
        }

        if (!$this->estaDisponible($doctorId, $fecha, $hora, (int) $servicio['duracion_min'])) {
            throw new InvalidArgumentException('Ese horario ya no está disponible, elige otro.');
        }

        $query = "INSERT INTO citas 
                    (paciente_id, doctor_id, servicio_id, fecha, hora, consultorio, estado, notas, costo)
                  VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?, ?)";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $pacienteId, $doctorId, $servicioId, $fecha, $hora,
            $doctor['consultorio'], $notas, $servicio['precio']
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    // ============================================================
    // MÉTODOS "ADMIN" — ven/editan TODAS las citas de la clínica,
    // no están filtrados por un solo paciente (a diferencia de los
    // métodos de arriba, que sí son "solo las mías").
    // ============================================================

    public function obtenerTodas(?string $busqueda, ?string $estado, ?int $doctorId): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "(up.nombre LIKE ? OR s.nombre LIKE ?)";
            $like = "%$busqueda%";
            array_push($parametros, $like, $like);
        }
        if ($estado) {
            $condiciones[] = "c.estado = ?";
            $parametros[] = $estado;
        }
        if ($doctorId) {
            $condiciones[] = "c.doctor_id = ?";
            $parametros[] = $doctorId;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

               $query = "SELECT 
                    c.id, c.fecha, c.hora, c.estado, c.notas, c.costo, c.tratamiento_id,
                    p.id AS paciente_id, up.nombre AS paciente_nombre, p.telefono,
                    d.id AS doctor_id, ud.nombre AS doctor_nombre,
                    s.id AS servicio_id, s.nombre AS servicio_nombre, s.duracion_min
                  FROM citas c
                  JOIN pacientes p ON c.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN doctores d ON c.doctor_id = d.id
                  JOIN usuarios ud ON d.usuario_id = ud.id
                  JOIN servicios s ON c.servicio_id = s.id
                  $where
                  ORDER BY c.fecha ASC, c.hora ASC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtenerPorIdAdmin(int $citaId): ?array {
        $query = "SELECT 
                    c.id, c.fecha, c.hora, c.estado, c.notas, c.costo,
                    p.id AS paciente_id, up.nombre AS paciente_nombre, p.telefono,
                    d.id AS doctor_id, s.id AS servicio_id
                  FROM citas c
                  JOIN pacientes p ON c.paciente_id = p.id
                  JOIN usuarios up ON p.usuario_id = up.id
                  JOIN doctores d ON c.doctor_id = d.id
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.id = ?";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$citaId]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

 public function crearAdmin(array $datos): int {
        $stmtServicio = $this->conexion->prepare("SELECT precio, duracion_min FROM servicios WHERE id = ? AND activo = 1");
        $stmtServicio->execute([$datos['servicioId']]);
        $servicio = $stmtServicio->fetch();
        if (!$servicio) {
            throw new InvalidArgumentException('Servicio no válido.');
        }

        $stmtDoctor = $this->conexion->prepare("SELECT consultorio FROM doctores WHERE id = ?");
        $stmtDoctor->execute([$datos['doctorId']]);
        $doctor = $stmtDoctor->fetch();
        if (!$doctor) {
            throw new InvalidArgumentException('Doctor no válido.');
        }

        $razon = $this->obtenerRazonNoDisponible($datos['doctorId'], $datos['fecha'], $datos['hora'], (int) $servicio['duracion_min']);
        if ($razon !== null) {
            throw new InvalidArgumentException($razon);
        }

        $query = "INSERT INTO citas 
                    (paciente_id, doctor_id, servicio_id, tratamiento_id, fecha, hora, consultorio, estado, notas, costo)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['pacienteId'], $datos['doctorId'], $datos['servicioId'], $datos['tratamientoId'] ?? null,
            $datos['fecha'], $datos['hora'], $doctor['consultorio'],
            $datos['estado'], $datos['notas'], $servicio['precio'],
        ]);

        return (int) $this->conexion->lastInsertId();
    }
    /**
     * Si esta cita está vinculada a un tratamiento, regresa su id --
     * CitaService lo usa al marcar una cita "completada" para saber
     * si debe sumarle una sesión a ese tratamiento.
     */
    public function obtenerTratamientoIdDeCita(int $citaId): ?int {
        $stmt = $this->conexion->prepare("SELECT tratamiento_id FROM citas WHERE id = ?");
        $stmt->execute([$citaId]);
        $valor = $stmt->fetchColumn();
        return $valor !== null && $valor !== false ? (int) $valor : null;
    }

    public function actualizarAdmin(int $citaId, array $datos): void {
        $stmtServicio = $this->conexion->prepare("SELECT precio, duracion_min FROM servicios WHERE id = ? AND activo = 1");
        $stmtServicio->execute([$datos['servicioId']]);
        $servicio = $stmtServicio->fetch();
        if (!$servicio) {
            throw new InvalidArgumentException('Servicio no válido.');
        }

        // Excluimos esta misma cita del choque de horarios (si no,
        // siempre chocaría consigo misma al editar sin cambiar nada).
        $razon = $this->obtenerRazonNoDisponible($datos['doctorId'], $datos['fecha'], $datos['hora'], (int) $servicio['duracion_min'], $citaId);
        if ($razon !== null) {
            throw new InvalidArgumentException($razon);
        }

        $query = "UPDATE citas SET 
                    paciente_id = ?, doctor_id = ?, servicio_id = ?, 
                    fecha = ?, hora = ?, estado = ?, notas = ?, costo = ?
                  WHERE id = ?";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['pacienteId'], $datos['doctorId'], $datos['servicioId'],
            $datos['fecha'], $datos['hora'], $datos['estado'], $datos['notas'],
            $servicio['precio'], $citaId,
        ]);
    }

    public function cancelarAdmin(int $citaId): void {
        $stmt = $this->conexion->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ?");
        $stmt->execute([$citaId]);
    }

    public function cancelar(int $citaId, int $pacienteId): bool {
        // El WHERE incluye paciente_id para que un paciente no pueda
        // cancelar la cita de otro adivinando el id.
        $stmt = $this->conexion->prepare(
            "UPDATE citas SET estado = 'cancelada' 
             WHERE id = ? AND paciente_id = ? AND estado IN ('pendiente','confirmada')"
        );
        $stmt->execute([$citaId, $pacienteId]);
        return $stmt->rowCount() > 0;
    }
    /**
     * Cancela todas las citas pendientes/confirmadas de un tratamiento --
     * se usa cuando el paciente rechaza el consentimiento y el tratamiento
     * completo se cancela, para no dejar citas "huérfanas" agendadas de
     * un tratamiento que ya no va a continuar.
     */
    public function cancelarPorTratamiento(int $tratamientoId): void {
        $stmt = $this->conexion->prepare(
            "UPDATE citas SET estado = 'cancelada' WHERE tratamiento_id = ? AND estado IN ('pendiente', 'confirmada')"
        );
        $stmt->execute([$tratamientoId]);
    }
}