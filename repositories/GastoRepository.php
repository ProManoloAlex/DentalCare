<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class GastoRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function listar(?string $busqueda, ?string $categoria, ?string $estado): array {
        $condiciones = [];
        $parametros = [];

        if ($busqueda) {
            $condiciones[] = "(descripcion LIKE ? OR proveedor LIKE ?)";
            $like = "%$busqueda%";
            array_push($parametros, $like, $like);
        }
        if ($categoria) {
            $condiciones[] = "categoria = ?";
            $parametros[] = $categoria;
        }
        if ($estado) {
            $condiciones[] = "estado = ?";
            $parametros[] = $estado;
        }

        $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $query = "SELECT id, categoria, descripcion, proveedor, fecha, monto, recurrente, estado, notas
                  FROM gastos $where
                  ORDER BY fecha DESC, id DESC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    /**
     * Totales por categoría + total general, usados en la pestaña
     * "Resumen" y en la vista de Gastos. Solo cuenta gastos del mes
     * en curso (igual que "Ingresos" en DashboardRepository).
     */
    public function obtenerResumenMesActual(): array {
        $porCategoria = $this->conexion->query(
            "SELECT categoria, COALESCE(SUM(monto), 0) AS total
             FROM gastos
             WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
             GROUP BY categoria"
        )->fetchAll();

        $total = (float) $this->conexion->query(
            "SELECT COALESCE(SUM(monto), 0) FROM gastos
             WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())"
        )->fetchColumn();

        $pendientes = (int) $this->conexion->query(
            "SELECT COUNT(*) FROM gastos WHERE estado = 'pendiente'"
        )->fetchColumn();

        return ['total_mes' => $total, 'por_categoria' => $porCategoria, 'pendientes' => $pendientes];
    }

    /**
     * Serie de gastos mensuales (para la gráfica Ingresos vs Gastos
     * del Resumen). Misma forma que DashboardRepository::obtenerIngresosMensuales().
     */
    public function obtenerGastosMensuales(int $meses): array {
        $query = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes_key, SUM(monto) AS total
                  FROM gastos
                  WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                  GROUP BY mes_key
                  ORDER BY mes_key ASC";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$meses]);
        return $stmt->fetchAll();
    }

    public function crear(array $datos): int {
        $query = "INSERT INTO gastos (categoria, descripcion, proveedor, fecha, monto, recurrente, estado, notas)
                  VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)";

        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['categoria'],
            $datos['descripcion'],
            $datos['proveedor'] ?: null,
            $datos['fecha'],
            $datos['monto'],
            !empty($datos['recurrente']) ? 1 : 0,
            $datos['notas'] ?: null,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function marcarPagado(int $id): void {
        $stmt = $this->conexion->prepare("UPDATE gastos SET estado = 'pagado' WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function eliminar(int $id): void {
        $stmt = $this->conexion->prepare("DELETE FROM gastos WHERE id = ?");
        $stmt->execute([$id]);
    }
}