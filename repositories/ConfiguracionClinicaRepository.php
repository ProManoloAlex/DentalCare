<?php

require_once __DIR__ . '/../config/Conexion_DB.php';

class ConfiguracionClinicaRepository {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function obtener(): array {
        $fila = $this->conexion->query("SELECT * FROM configuracion_clinica WHERE id = 1")->fetch();
        return $fila ?: [];
    }

    public function guardar(array $datos): void {
        $query = "UPDATE configuracion_clinica SET
                    nombre = ?, slogan = ?, telefono_principal = ?, telefono_emergencia = ?,
                    correo = ?, sitio_web = ?, direccion = ?, ciudad = ?, estado_provincia = ?,
                    codigo_postal = ?, pais = ?, rfc = ?, razon_social = ?, moneda = ?, iva_porcentaje = ?,
                    duracion_cita_default_min = ?, intervalo_citas_min = ?, anticipacion_max_dias = ?
                  WHERE id = 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([
            $datos['nombre'] ?: null, $datos['slogan'] ?: null, $datos['telefonoPrincipal'] ?: null,
            $datos['telefonoEmergencia'] ?: null, $datos['correo'] ?: null, $datos['sitioWeb'] ?: null,
            $datos['direccion'] ?: null, $datos['ciudad'] ?: null, $datos['estadoProvincia'] ?: null,
            $datos['codigoPostal'] ?: null, $datos['pais'] ?: null, $datos['rfc'] ?: null,
            $datos['razonSocial'] ?: null, $datos['moneda'] ?: 'MXN', $datos['ivaPorcentaje'] ?: 0,
            $datos['duracionCitaMin'] ?: 30, $datos['intervaloCitasMin'] ?: 15, $datos['anticipacionMaxDias'] ?: 90,
        ]);
    }

    public function obtenerHorarios(): array {
        return $this->conexion->query("SELECT * FROM horarios_atencion ORDER BY dia_semana ASC")->fetchAll();
    }

    /**
     * @param array $horarios cada elemento: ['dia' => int, 'activo' => bool, 'inicio' => 'HH:MM', 'fin' => 'HH:MM']
     */
    public function guardarHorarios(array $horarios): void {
        $stmt = $this->conexion->prepare(
            "UPDATE horarios_atencion SET activo = ?, hora_inicio = ?, hora_fin = ? WHERE dia_semana = ?"
        );
        foreach ($horarios as $h) {
            $stmt->execute([
                !empty($h['activo']) ? 1 : 0,
                !empty($h['activo']) ? $h['inicio'] : null,
                !empty($h['activo']) ? $h['fin'] : null,
                $h['dia'],
            ]);
        }
    }
}
