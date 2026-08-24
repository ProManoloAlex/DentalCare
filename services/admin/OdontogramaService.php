<?php

require_once __DIR__ . '/../../repositories/OdontogramaRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';

class OdontogramaService {
    private OdontogramaRepository $repo;

    public const CONDICIONES_VALIDAS = [
        'sano', 'caries', 'obturado', 'extraido', 'implante', 'corona', 'endodoncia', 'fractura',
    ];

    public function __construct() {
        $this->repo = new OdontogramaRepository();
    }

    public function obtener(int $pacienteId): array {
        $filas = $this->repo->obtenerPorPaciente($pacienteId);

        // Arrancamos con los 32 dientes en "sano" y sobreescribimos
        // con lo que sí exista guardado — así nunca falta un diente
        // aunque el paciente solo tenga 1 o 2 condiciones registradas.
        $dientes = [];
        for ($i = 1; $i <= 32; $i++) {
            $dientes[$i] = ['condicion' => 'sano', 'notas' => ''];
        }
        foreach ($filas as $fila) {
            $dientes[(int) $fila['numero_diente']] = [
                'condicion' => $fila['condicion'],
                'notas' => $fila['notas'] ?? '',
            ];
        }

        $ultimaActualizacion = $this->repo->obtenerUltimaActualizacion($pacienteId);

        return [
            'ok' => true,
            'dientes' => $dientes,
            'ultimaActualizacion' => $ultimaActualizacion ? (new DateTime($ultimaActualizacion))->format('d/m/Y') : 'Sin registros',
        ];
    }

    public function guardar(int $pacienteId, array $dientes): array {
        foreach ($dientes as $numero => $datos) {
            $numero = (int) $numero;
            if ($numero < 1 || $numero > 32) {
                return ['ok' => false, 'mensaje' => "Número de diente inválido: $numero."];
            }
            if (!in_array($datos['condicion'] ?? '', self::CONDICIONES_VALIDAS, true)) {
                return ['ok' => false, 'mensaje' => "Condición inválida para el diente $numero."];
            }
            if (isset($datos['notas']) && strlen($datos['notas']) > 500) {
                return ['ok' => false, 'mensaje' => "Las notas del diente $numero superan los 500 caracteres."];
            }
        }

        $this->repo->guardarCompleto($pacienteId, $dientes);
        Auditoria::registrar('odontograma', 'Actualizó el odontograma', "Paciente #$pacienteId");
        return ['ok' => true];
    }

    public function listarPacientes(?string $busqueda): array {
        $pacientes = $this->repo->listarPacientesConResumen($busqueda);

        return array_map(fn($p) => [
            'id' => (int) $p['id'],
            'nombre' => $p['nombre'],
            'ultimaActualizacion' => $p['ultima_actualizacion']
                ? (new DateTime($p['ultima_actualizacion']))->format('d/m/Y')
                : 'Sin registros',
        ], $pacientes);
    }
}