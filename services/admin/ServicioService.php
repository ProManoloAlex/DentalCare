<?php

require_once __DIR__ . '/../../repositories/ServicioRepository.php';

class ServicioService {
    private ServicioRepository $repo;

    // Aquí viven las únicas categorías válidas: el valor (sin acento)
    // es lo que se guarda en la BD, la etiqueta es lo que ve el doctor.
    public const CATEGORIAS = [
        'Preventivo'   => 'Preventivo',
        'Restaurativo' => 'Restaurativo',
        'Estetico'     => 'Estético',
        'Cirugia'      => 'Cirugía',
        'Ortodoncia'   => 'Ortodoncia',
    ];

    public function __construct() {
        $this->repo = new ServicioRepository();
    }

    public function listar(?string $busqueda, ?string $categoria, string $orden): array {
        $servicios = $this->repo->listar($busqueda, $categoria, $orden);

        return array_map(fn($s) => [
            'id'          => (int) $s['id'],
            'nombre'      => $s['nombre'],
            'categoria'   => $s['categoria'],
            'descripcion' => $s['descripcion'],
            'duracion'    => (int) $s['duracion_min'],
            'precio'      => (float) $s['precio'],
            'activo'      => (bool) $s['activo'],
        ], $servicios);
    }

    public function obtenerPorId(int $id): array {
        $s = $this->repo->obtenerPorId($id);
        if (!$s) {
            return ['ok' => false, 'mensaje' => 'Servicio no encontrado.'];
        }
        return [
            'ok' => true,
            'id' => (int) $s['id'],
            'nombre' => $s['nombre'],
            'categoria' => $s['categoria'],
            'descripcion' => $s['descripcion'],
            'duracionMin' => (int) $s['duracion_min'],
            'precio' => (float) $s['precio'],
        ];
    }

    public function crear(array $datos): array {
        $validacion = $this->validar($datos);
        if ($validacion !== null) return $validacion;

        $id = $this->repo->crear($datos);
        return ['ok' => true, 'id' => $id];
    }

    public function actualizar(int $id, array $datos): array {
        $validacion = $this->validar($datos);
        if ($validacion !== null) return $validacion;

        $this->repo->actualizar($id, $datos);
        return ['ok' => true];
    }

    public function cambiarEstado(int $id, bool $activo): array {
        $this->repo->cambiarEstado($id, $activo);
        return ['ok' => true];
    }

    public function eliminar(int $id): array {
        $eliminado = $this->repo->eliminar($id);

        if (!$eliminado) {
            return [
                'ok' => false,
                'mensaje' => 'Este servicio ya se usó en al menos una cita y no se puede eliminar '
                           . '(se perdería ese historial). Puedes pausarlo en vez de borrarlo.',
            ];
        }

        return ['ok' => true];
    }

    private function validar(array $datos): ?array {
        if (empty($datos['nombre']) || empty($datos['categoria']) || empty($datos['precio'])) {
            return ['ok' => false, 'mensaje' => 'Faltan campos obligatorios.'];
        }
        if (!array_key_exists($datos['categoria'], self::CATEGORIAS)) {
            return ['ok' => false, 'mensaje' => 'Categoría no válida.'];
        }
        return null;
    }
}