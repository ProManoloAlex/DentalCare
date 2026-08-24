<?php

require_once __DIR__ . '/../../repositories/PagoRepository.php';
require_once __DIR__ . '/../../repositories/GastoRepository.php';
require_once __DIR__ . '/../../repositories/FacturaRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';

class FinanzasService {
    private PagoRepository $pagoRepo;
    private GastoRepository $gastoRepo;
    private FacturaRepository $facturaRepo;

    public function __construct() {
        $this->pagoRepo = new PagoRepository();
        $this->gastoRepo = new GastoRepository();
        $this->facturaRepo = new FacturaRepository();
    }

    /**
     * Todo lo que pinta la pestaña "Resumen": KPIs + serie mensual
     * de ingresos vs. gastos para la gráfica.
     */
    public function obtenerResumen(): array {
        $pagosMes = $this->pagoRepo->obtenerResumenMesActual();
        $gastosMes = $this->gastoRepo->obtenerResumenMesActual();
        $facturas = $this->facturaRepo->obtenerResumen();

        $ingresosPorMes = $this->indexarPorMes($this->pagoRepoIngresosMensuales(6));
        $gastosPorMes = $this->indexarPorMes($this->gastoRepo->obtenerGastosMensuales(6));

        $meses = array_unique(array_merge(array_keys($ingresosPorMes), array_keys($gastosPorMes)));
        sort($meses);

        $serieGrafica = array_map(fn($mes) => [
            'mes' => $mes,
            'ingresos' => $ingresosPorMes[$mes] ?? 0,
            'gastos' => $gastosPorMes[$mes] ?? 0,
        ], $meses);

        return [
            'ingresos_mes' => $pagosMes['total_mes'],
            'gastos_mes' => $gastosMes['total_mes'],
            'utilidad_mes' => $pagosMes['total_mes'] - $gastosMes['total_mes'],
            'saldo_pendiente' => $facturas['total_pendiente'],
            'total_facturas' => $facturas['total_facturas'],
            'gastos_pendientes' => $gastosMes['pendientes'],
            'serie_grafica' => $serieGrafica,
        ];
    }

    // Pequeño puente porque DashboardRepository ya tiene esta misma
    // consulta de ingresos mensuales -- se reutiliza en vez de duplicarla.
    private function pagoRepoIngresosMensuales(int $meses): array {
        require_once __DIR__ . '/../../repositories/DashboardRepository.php';
        return (new DashboardRepository())->obtenerIngresosMensuales($meses);
    }

    private function indexarPorMes(array $filas): array {
        $indexado = [];
        foreach ($filas as $fila) {
            $indexado[$fila['mes_key']] = (float) $fila['total'];
        }
        return $indexado;
    }

    public function listarFacturas(?string $busqueda, ?string $estado): array {
        return $this->facturaRepo->listarDerivadas($busqueda, $estado);
    }

    public function listarPagos(?string $busqueda, ?string $metodo): array {
        return $this->pagoRepo->listarConDetalle($busqueda, $metodo);
    }

    public function obtenerSaldosDePaciente(int $pacienteId): array {
        return $this->pagoRepo->obtenerSaldosDePaciente($pacienteId);
    }

    /**
     * Registra un pago. Solo el doctor decide el monto y a qué se
     * aplica -- este método no calcula nada por su cuenta, confía
     * en lo que el doctor capturó en el formulario (a diferencia de
     * CitaRepository::crear, aquí no hay un "precio de catálogo" que
     * pisar, porque un pago no es un servicio con precio fijo).
     */
    public function registrarPago(array $datos): int {
        if (empty($datos['pacienteId'])) {
            throw new InvalidArgumentException('Selecciona un paciente.');
        }
        if (empty($datos['monto']) || (float) $datos['monto'] <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor a 0.');
        }
        if (empty($datos['metodo']) || !in_array($datos['metodo'], ['efectivo', 'tarjeta', 'transferencia'], true)) {
            throw new InvalidArgumentException('Método de pago no válido.');
        }
        if (empty($datos['fechaPago'])) {
            throw new InvalidArgumentException('La fecha de pago es obligatoria.');
        }
        if (!empty($datos['tratamientoId']) && !empty($datos['citaId'])) {
            throw new InvalidArgumentException('Un pago se aplica a un tratamiento o a una cita, no a los dos.');
        }

        $pagoId = $this->pagoRepo->crear($datos);
        Auditoria::registrar('finanzas', 'Registró un pago', 'Pago #' . $pagoId . ' por $' . $datos['monto']);
        return $pagoId;
    }

    public function listarGastos(?string $busqueda, ?string $categoria, ?string $estado): array {
        return $this->gastoRepo->listar($busqueda, $categoria, $estado);
    }

    public function registrarGasto(array $datos): int {
        if (empty($datos['categoria'])) {
            throw new InvalidArgumentException('La categoría es obligatoria.');
        }
        if (empty($datos['descripcion'])) {
            throw new InvalidArgumentException('La descripción es obligatoria.');
        }
        if (empty($datos['monto']) || (float) $datos['monto'] <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor a 0.');
        }
        if (empty($datos['fecha'])) {
            throw new InvalidArgumentException('La fecha es obligatoria.');
        }

        $gastoId = $this->gastoRepo->crear($datos);
        Auditoria::registrar('finanzas', 'Registró un gasto', $datos['descripcion'] . ' — $' . $datos['monto']);
        return $gastoId;
    }

    public function marcarGastoPagado(int $gastoId): void {
        $this->gastoRepo->marcarPagado($gastoId);
        Auditoria::registrar('finanzas', 'Marcó un gasto como pagado', "Gasto #$gastoId");
    }
}