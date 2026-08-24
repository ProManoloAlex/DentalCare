<?php
require_once __DIR__ . '/../_verificar_sesion.php';
verificarSesionDoctor();
require_once __DIR__ . '/../../../services/admin/ReporteService.php';

/**
 * Exporta el reporte activo a CSV (Excel lo abre directo). No genera
 * ningún archivo en disco -- arma el CSV en memoria y lo manda como
 * descarga. Reutiliza exactamente los mismos métodos del Service que
 * ya usa la vista en pantalla, así que nunca se puede desincronizar
 * de lo que el doctor está viendo.
 */

$tab = $_GET['tab'] ?? 'general';
$periodo = $_GET['periodo'] ?? null;

function csvLinea(array $campos): string {
    $escapados = array_map(function ($c) {
        return '"' . str_replace('"', '""', (string) $c) . '"';
    }, $campos);
    return implode(',', $escapados) . "\r\n";
}

$service = new ReporteService();
$contenido = '';
$nombreArchivo = 'reporte';

switch ($tab) {
    case 'financiero':
        $r = $service->obtenerFinanciero($periodo);
        $contenido .= csvLinea(['Detalle Mensual — ' . $r['periodoTexto']]);
        $contenido .= csvLinea(['Mes', 'Ingresos', 'Gastos', 'Utilidad']);
        foreach ($r['detalleMensual'] as $m) {
            $contenido .= csvLinea([$m['mes'], $m['ingresos'], $m['gastos'], $m['ingresos'] - $m['gastos']]);
        }
        $contenido .= csvLinea([]);
        $contenido .= csvLinea(['Métodos de Pago']);
        $contenido .= csvLinea(['Método', 'Total']);
        foreach ($r['metodosPago'] as $m) {
            $contenido .= csvLinea([$m['metodo'], $m['total']]);
        }
        $nombreArchivo = 'reporte-financiero';
        break;

    case 'pacientes':
        $r = $service->obtenerPacientes();
        $contenido .= csvLinea(['Métricas Generales']);
        $contenido .= csvLinea(['Tasa de Retención', $r['tasaRetencion'] . '%']);
        $contenido .= csvLinea(['Visitas Promedio', $r['visitasPromedio']]);
        $contenido .= csvLinea([]);
        $contenido .= csvLinea(['Nuevos Pacientes por Mes']);
        $contenido .= csvLinea(['Mes', 'Total']);
        foreach ($r['nuevosPorMes'] as $m) { $contenido .= csvLinea([$m['mes_key'], $m['total']]); }
        $contenido .= csvLinea([]);
        $contenido .= csvLinea(['Distribución por Género']);
        $contenido .= csvLinea(['Género', 'Total']);
        foreach ($r['distribucionGenero'] as $g) { $contenido .= csvLinea([$g['genero'], $g['total']]); }
        $contenido .= csvLinea([]);
        $contenido .= csvLinea(['Distribución por Edad']);
        $contenido .= csvLinea(['Rango', 'Total', '%']);
        foreach ($r['distribucionEdad'] as $g) { $contenido .= csvLinea([$g['rango'], $g['total'], $g['pct']]); }
        $contenido .= csvLinea([]);
        $contenido .= csvLinea(['Citas por Día de la Semana']);
        $contenido .= csvLinea(['Día', 'Total']);
        foreach ($r['citasPorDiaSemana'] as $d) { $contenido .= csvLinea([$d['dia'], $d['total']]); }
        $nombreArchivo = 'reporte-pacientes';
        break;

    case 'tratamientos':
        $r = $service->obtenerTratamientos($periodo);
        $contenido .= csvLinea(['Resumen por Categoría — ' . $r['periodoTexto']]);
        $contenido .= csvLinea(['Categoría', 'Total', 'Monto']);
        foreach ($r['categorias'] as $c) { $contenido .= csvLinea([$c['categoria'], $c['total'], $c['monto']]); }
        $contenido .= csvLinea([]);
        $contenido .= csvLinea(['Ranking de Servicios']);
        $contenido .= csvLinea(['Servicio', 'Categoría', 'Realizados', 'Monto']);
        foreach ($r['ranking'] as $t) { $contenido .= csvLinea([$t['nombre'], $t['categoria'], $t['realizados'], $t['monto']]); }
        $nombreArchivo = 'reporte-tratamientos';
        break;

    case 'dentistas':
        $r = $service->obtenerDentistas($periodo);
        $contenido .= csvLinea(['Equipo — ' . $r['periodoTexto']]);
        $contenido .= csvLinea(['Total Citas', $r['totalCitasEquipo']]);
        $contenido .= csvLinea(['Pacientes Atendidos', $r['pacientesAtendidosEquipo']]);
        $contenido .= csvLinea(['Ingresos Generados', $r['ingresosEquipo']]);
        $contenido .= csvLinea([]);
        $contenido .= csvLinea(['Dentista', 'Especialidad', 'Citas Totales', 'Citas Completadas', '% Completadas', 'Pacientes', 'Tratamientos Activos', 'Ingresos', '% Ingresos', 'Top Servicio']);
        foreach ($r['dentistas'] as $d) {
            $contenido .= csvLinea([
                $d['nombre'], $d['especialidad'], $d['citasTotal'], $d['citasCompletadas'], $d['pctCompletadas'],
                $d['pacientesAtendidos'], $d['tratamientosActivos'], $d['ingresos'], $d['pctIngresos'], $d['topServicio'] ?? '',
            ]);
        }
        $nombreArchivo = 'reporte-dentistas';
        break;

    default: // general
        $r = $service->obtenerGeneral($periodo);
        $k = $r['kpis'];
        $contenido .= csvLinea(['KPIs Generales — ' . $r['periodoTexto']]);
        $contenido .= csvLinea(['Pacientes Totales', $k['pacientesTotales']]);
        $contenido .= csvLinea(['Pacientes Nuevos', $k['pacientesNuevos']]);
        $contenido .= csvLinea(['Citas Programadas', $k['citasProgramadas']]);
        $contenido .= csvLinea(['Citas Completadas', $k['citasCompletadas']]);
        $contenido .= csvLinea(['Citas Canceladas', $k['citasCanceladas']]);
        $contenido .= csvLinea(['Tasa de Asistencia', $k['tasaAsistencia'] . '%']);
        $contenido .= csvLinea(['Ingresos', $k['ingresos']]);
        $contenido .= csvLinea(['Gastos', $k['gastos']]);
        $contenido .= csvLinea(['Utilidad Neta', $k['utilidadNeta']]);
        $contenido .= csvLinea(['Margen de Utilidad', $k['margenUtilidad'] . '%']);
        $contenido .= csvLinea(['Tratamientos Activos', $k['tratamientosActivos']]);
        $contenido .= csvLinea(['Tratamientos Completados', $k['tratamientosCompletados']]);
        $contenido .= csvLinea([]);
        $contenido .= csvLinea(['Citas por Mes']);
        $contenido .= csvLinea(['Mes', 'Total']);
        foreach ($r['citasPorMes'] as $m) { $contenido .= csvLinea([$m['mes_key'], $m['total']]); }
        $contenido .= csvLinea([]);
        $contenido .= csvLinea(['Pacientes Atendidos por Mes']);
        $contenido .= csvLinea(['Mes', 'Total']);
        foreach ($r['pacientesPorMes'] as $m) { $contenido .= csvLinea([$m['mes_key'], $m['total']]); }
        $nombreArchivo = 'reporte-general';
        break;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '-' . ($periodo ?: date('Y-m')) . '.csv"');
echo "\xEF\xBB\xBF"; // BOM: para que Excel detecte UTF-8 y no rompa acentos/ñ
echo $contenido;