<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class PacienteRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    /**
     * Listado completo para la tabla de "Gestión de Pacientes".
     * Cada columna calculada (visitas, última visita, próxima cita,
     * saldo) sale de una subconsulta — nada se guarda como campo fijo,
     * siempre se calcula en vivo desde citas/tratamientos/pagos.
     */
    public function obtenerListado(?string $busqueda, ?string $estado, string $orden): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "(u.nombre LIKE ? OR u.correo LIKE ? OR p.telefono LIKE ? OR CONCAT('P-', LPAD(p.id, 3, '0')) LIKE ?)";
            $like = "%$busqueda%";
            array_push($parametros, $like, $like, $like, $like);
        }

        if ($estado === 'activo') {
            $condiciones[] = "u.activo = 1";
        } elseif ($estado === 'inactivo') {
            $condiciones[] = "u.activo = 0";
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $ordenSql = match ($orden) {
            'ultima_visita' => 'ultima_visita DESC',
            'proxima_cita'  => 'proxima_cita ASC',
            default         => 'u.nombre ASC',
        };

        $query = "SELECT 
                    p.id AS paciente_id,
                    u.nombre, u.correo, u.activo,
                    p.telefono, p.fecha_nacimiento,
                    (SELECT MAX(c.fecha) FROM citas c WHERE c.paciente_id = p.id AND c.estado = 'completada') AS ultima_visita,
                    (SELECT MIN(c.fecha) FROM citas c WHERE c.paciente_id = p.id AND c.estado IN ('pendiente','confirmada') AND c.fecha >= CURDATE()) AS proxima_cita,
                    (SELECT COUNT(*) FROM citas c WHERE c.paciente_id = p.id AND c.estado = 'completada') AS visitas,
                    (
                        COALESCE((SELECT SUM(t.costo_total) FROM tratamientos t WHERE t.paciente_id = p.id AND t.estado != 'cancelado'), 0)
                        - COALESCE((SELECT SUM(pg.monto) FROM pagos pg JOIN tratamientos t2 ON pg.tratamiento_id = t2.id WHERE t2.paciente_id = p.id), 0)
                        + COALESCE((SELECT SUM(c.costo) FROM citas c WHERE c.paciente_id = p.id AND c.pagado = 0 AND c.estado != 'cancelada'), 0)
                    ) AS saldo
                  FROM pacientes p
                  JOIN usuarios u ON p.usuario_id = u.id
                  $where
                  ORDER BY $ordenSql";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtenerResumen(): array {
        $total = $this->conexion->query("SELECT COUNT(*) FROM pacientes")->fetchColumn();

        $activos = $this->conexion->query(
            "SELECT COUNT(*) FROM pacientes p JOIN usuarios u ON p.usuario_id = u.id WHERE u.activo = 1"
        )->fetchColumn();

        $conCitasProximas = $this->conexion->query(
            "SELECT COUNT(DISTINCT paciente_id) FROM citas 
             WHERE estado IN ('pendiente','confirmada') AND fecha >= CURDATE()"
        )->fetchColumn();

        // Con saldo pendiente: reutilizamos la misma lógica de "saldo" del listado,
        // pero solo contamos cuántos pacientes distintos tienen saldo > 0.
        $conSaldo = $this->conexion->query(
            "SELECT COUNT(*) FROM (
                SELECT p.id,
                    (
                        COALESCE((SELECT SUM(t.costo_total) FROM tratamientos t WHERE t.paciente_id = p.id AND t.estado != 'cancelado'), 0)
                        - COALESCE((SELECT SUM(pg.monto) FROM pagos pg JOIN tratamientos t2 ON pg.tratamiento_id = t2.id WHERE t2.paciente_id = p.id), 0)
                        + COALESCE((SELECT SUM(c.costo) FROM citas c WHERE c.paciente_id = p.id AND c.pagado = 0 AND c.estado != 'cancelada'), 0)
                    ) AS saldo
                FROM pacientes p
             ) sub WHERE saldo > 0"
        )->fetchColumn();

        return [
            'total'             => (int) $total,
            'activos'           => (int) $activos,
            'con_citas_proximas'=> (int) $conCitasProximas,
            'con_saldo'         => (int) $conSaldo,
        ];
    }

    /**
     * Crea el paciente. Si $contrasenna es null, la cuenta queda sin
     * acceso al portal (login bloqueado hasta que se le asigne una).
     */
    public function crear(array $datos, ?string $contrasenna): int {
        $this->conexion->beginTransaction();
        try {
            $hash = $contrasenna !== null ? password_hash($contrasenna, PASSWORD_BCRYPT) : null;

            $stmtUsuario = $this->conexion->prepare(
                "INSERT INTO usuarios (nombre, correo, contrasenna, rol) VALUES (?, ?, ?, 'paciente')"
            );
            $stmtUsuario->execute([$datos['nombre'], $datos['email'], $hash]);
            $usuarioId = (int) $this->conexion->lastInsertId();

            $stmtPaciente = $this->conexion->prepare(
                "INSERT INTO pacientes 
                    (usuario_id, telefono, fecha_nacimiento, genero, direccion, tipo_sangre, alergias, contacto_emergencia)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmtPaciente->execute([
                $usuarioId,
                $datos['telefono'],
                $datos['fechaNacimiento'],
                $datos['genero'] ?: null,
                $datos['direccion'] ?: null,
                $datos['tipoSangre'] ?: null,
                $datos['alergias'] ?: null,
                $datos['contactoEmergencia'] ?: null,
            ]);
            $pacienteId = (int) $this->conexion->lastInsertId();

            $this->conexion->commit();
            return $pacienteId;
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    /**
     * Detalle completo de un paciente (para el modal "Ver detalle"
     * y para precargar el formulario de "Editar").
     */
    public function obtenerDetalle(int $pacienteId): ?array {
        $query = "SELECT 
                    p.id AS paciente_id, p.usuario_id, p.telefono, p.fecha_nacimiento,
                    p.genero, p.direccion, p.tipo_sangre, p.alergias, p.contacto_emergencia,
                    u.nombre, u.correo, u.activo,
                    (u.contrasenna IS NOT NULL) AS tiene_acceso,
                    (SELECT MAX(c.fecha) FROM citas c WHERE c.paciente_id = p.id AND c.estado = 'completada') AS ultima_visita,
                    (SELECT MIN(c.fecha) FROM citas c WHERE c.paciente_id = p.id AND c.estado IN ('pendiente','confirmada') AND c.fecha >= CURDATE()) AS proxima_cita,
                    (SELECT COUNT(*) FROM citas c WHERE c.paciente_id = p.id AND c.estado = 'completada') AS visitas,
                    (
                        COALESCE((SELECT SUM(t.costo_total) FROM tratamientos t WHERE t.paciente_id = p.id AND t.estado != 'cancelado'), 0)
                        - COALESCE((SELECT SUM(pg.monto) FROM pagos pg JOIN tratamientos t2 ON pg.tratamiento_id = t2.id WHERE t2.paciente_id = p.id), 0)
                        + COALESCE((SELECT SUM(c.costo) FROM citas c WHERE c.paciente_id = p.id AND c.pagado = 0 AND c.estado != 'cancelada'), 0)
                    ) AS saldo
                  FROM pacientes p
                  JOIN usuarios u ON p.usuario_id = u.id
                  WHERE p.id = ?";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    public function actualizar(int $pacienteId, array $datos): void {
        $this->conexion->beginTransaction();
        try {
            $stmtUsuarioId = $this->conexion->prepare("SELECT usuario_id FROM pacientes WHERE id = ?");
            $stmtUsuarioId->execute([$pacienteId]);
            $usuarioId = $stmtUsuarioId->fetchColumn();

            if (!$usuarioId) {
                throw new InvalidArgumentException('Paciente no encontrado.');
            }

            $stmtUsuario = $this->conexion->prepare("UPDATE usuarios SET nombre = ?, correo = ? WHERE id = ?");
            $stmtUsuario->execute([$datos['nombre'], $datos['email'], $usuarioId]);

            $stmtPaciente = $this->conexion->prepare(
                "UPDATE pacientes SET 
                    telefono = ?, fecha_nacimiento = ?, genero = ?, direccion = ?, 
                    tipo_sangre = ?, alergias = ?, contacto_emergencia = ?
                 WHERE id = ?"
            );
            $stmtPaciente->execute([
                $datos['telefono'], $datos['fechaNacimiento'], $datos['genero'] ?: null,
                $datos['direccion'] ?: null, $datos['tipoSangre'] ?: null,
                $datos['alergias'] ?: null, $datos['contactoEmergencia'] ?: null,
                $pacienteId,
            ]);

            $this->conexion->commit();
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    public function otorgarAcceso(int $pacienteId, string $contrasenaTemporal): void {
        $hash = password_hash($contrasenaTemporal, PASSWORD_BCRYPT);
        $stmt = $this->conexion->prepare(
            "UPDATE usuarios u 
             JOIN pacientes p ON p.usuario_id = u.id 
             SET u.contrasenna = ? 
             WHERE p.id = ?"
        );
        $stmt->execute([$hash, $pacienteId]);
    }

    public function cambiarEstado(int $pacienteId, bool $activo): void {
        $stmt = $this->conexion->prepare(
            "UPDATE usuarios u 
             JOIN pacientes p ON p.usuario_id = u.id 
             SET u.activo = ? 
             WHERE p.id = ?"
        );
        $stmt->execute([$activo ? 1 : 0, $pacienteId]);
    }

    public function correoExisteExcluyendo(string $correo, int $usuarioIdExcluir): bool {
        $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = ? AND id != ?");
        $stmt->execute([$correo, $usuarioIdExcluir]);
        return $stmt->fetchColumn() > 0;
    }

    public function correoExiste(string $correo): bool {
        $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = ?");
        $stmt->execute([$correo]);
        return $stmt->fetchColumn() > 0;
    }
    
    public function obtenerHistorialCitas(int $pacienteId): array {
        $query = "SELECT c.fecha, c.hora, s.nombre AS servicio, c.costo, c.pagado, c.estado
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE c.paciente_id = ?
                  ORDER BY c.fecha DESC, c.hora DESC";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$pacienteId]);
        return $stmt->fetchAll();
    }
}