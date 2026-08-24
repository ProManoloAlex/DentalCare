<?php

require_once __DIR__ . '/../../repositories/RecetaRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';

class RecetaService {
    private RecetaRepository $repo;

    public function __construct() {
        $this->repo = new RecetaRepository();
    }

    public function listar(?string $busqueda, ?string $estado): array {
        return $this->repo->listar($busqueda, $estado ? strtolower($estado) : null);
    }

    public function obtenerResumen(): array {
        $porEstado = $this->repo->contarPorEstado();
        $total = array_sum($porEstado);

        return [
            'kpis' => [
                'recetasActivas' => $porEstado['activa'],
                'emitidasEsteMes' => $this->repo->contarEmitidasEsteMes(),
                'pacientesAtendidos' => $this->repo->contarPacientesUnicos(),
                'totalMedicamentos' => $this->repo->contarTotalMedicamentos(),
            ],
            'totalRecetas' => $total,
            'porEstado' => $porEstado,
            'recientesActivas' => $this->repo->obtenerActivasRecientes(5),
            'medicamentosTop' => $this->repo->obtenerMedicamentosMasPrescritos(4),
        ];
    }

    public function registrarReceta(array $datos, array $medicamentos): int {
        if (empty($datos['pacienteId'])) {
            throw new InvalidArgumentException('Selecciona un paciente.');
        }
        if (empty($datos['diagnostico'])) {
            throw new InvalidArgumentException('El diagnóstico es obligatorio.');
        }
        if (empty($datos['fecha'])) {
            $datos['fecha'] = date('Y-m-d');
        }

        $medicamentosLimpios = array_values(array_filter($medicamentos, fn($m) => !empty($m['nombre']) && !empty($m['dosis'])));
        $recetaId = $this->repo->crear($datos, $medicamentosLimpios);
        Auditoria::registrar('recetas', 'Generó una receta', 'Diagnóstico: ' . $datos['diagnostico']);
        return $recetaId;
    }

    public function cambiarEstado(int $recetaId, string $nuevoEstado): void {
        if (!in_array($nuevoEstado, ['activa', 'completada', 'vencida'], true)) {
            throw new InvalidArgumentException('Estado no válido.');
        }
        $this->repo->cambiarEstado($recetaId, $nuevoEstado);
        Auditoria::registrar('recetas', 'Cambió el estado de una receta a ' . $nuevoEstado, "Receta #$recetaId");
    }

    public function actualizarReceta(int $recetaId, array $datos, array $medicamentos): void {
        if (empty($datos['pacienteId'])) {
            throw new InvalidArgumentException('Selecciona un paciente.');
        }
        if (empty($datos['diagnostico'])) {
            throw new InvalidArgumentException('El diagnóstico es obligatorio.');
        }
        $medicamentosLimpios = array_values(array_filter($medicamentos, fn($m) => !empty($m['nombre']) && !empty($m['dosis'])));
        $this->repo->actualizar($recetaId, $datos, $medicamentosLimpios);
        Auditoria::registrar('recetas', 'Editó una receta', "Receta #$recetaId");
    }

    public function listarParaPaciente(int $pacienteId): array {
        return $this->repo->listarPorPaciente($pacienteId);
    }  
}