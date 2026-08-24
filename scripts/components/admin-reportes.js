// ============================================================
// MÓDULO REPORTES — conectado al backend real. Solo lectura.
// Mismo patrón de siempre: una función activar*() por responsabilidad.
// ============================================================

function money(n) {
  return '$' + Number(n || 0).toLocaleString('es-MX');
}

const NOMBRES_MES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

document.addEventListener('DOMContentLoaded', function () {
  activarSelectorPeriodo();
  activarTabs();
  activarExportar();

  cargarGeneral();
});

// ---------- PERIODO ----------

function periodoActual() {
  return document.getElementById('selectorPeriodo').value;
}

function activarSelectorPeriodo() {
  const select = document.getElementById('selectorPeriodo');
  const hoy = new Date();
  let opciones = '';

  for (let i = 0; i < 6; i++) {
    const fecha = new Date(hoy.getFullYear(), hoy.getMonth() - i, 1);
    const valor = `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}`;
    opciones += `<option value="${valor}">${NOMBRES_MES[fecha.getMonth()]} ${fecha.getFullYear()}</option>`;
  }
  select.innerHTML = opciones;

  select.addEventListener('change', () => {
    // Al cambiar el periodo se vuelve a pedir SOLO la pestaña activa
    // (las demás se recargan la próxima vez que se abran).
    Object.keys(cargadoPorTab).forEach((k) => (cargadoPorTab[k] = false));
    const tabActivo = document.querySelector('.tabs-rep .btn-teal').id;
    cargarTab(tabActivo);
  });
}

// ---------- TABS ----------

const cargadoPorTab = { tabGeneral: false, tabFinanciero: false, tabPacientes: false, tabTratamientos: false, tabDentistas: false };

function activarTabs() {
  const tabs = { tabGeneral: 'vistaGeneral', tabFinanciero: 'vistaFinanciero', tabPacientes: 'vistaPacientes', tabTratamientos: 'vistaTratamientos', tabDentistas: 'vistaDentistas' };

  Object.keys(tabs).forEach((tabId) => {
    document.getElementById(tabId).addEventListener('click', function () {
      Object.entries(tabs).forEach(([btnId, viewId]) => {
        document.getElementById(viewId).classList.toggle('d-none', btnId !== tabId);
        document.getElementById(btnId).classList.toggle('btn-teal', btnId === tabId);
        document.getElementById(btnId).classList.toggle('btn-outline-soft', btnId !== tabId);
      });
      cargarTab(tabId);
    });
  });

  cargadoPorTab.tabGeneral = true;
}

function cargarTab(tabId) {
  if (cargadoPorTab[tabId]) return;
  cargadoPorTab[tabId] = true;

  ({
    tabGeneral: cargarGeneral,
    tabFinanciero: cargarFinanciero,
    tabPacientes: cargarPacientes,
    tabTratamientos: cargarTratamientos,
    tabDentistas: cargarDentistas,
  })[tabId]();
}

// ---------- GENERAL ----------

let chartCitasMes = null, chartPacientesMes = null;

async function cargarGeneral() {
  try {
    const res = await fetch(`/api/admin/reportes/general.php?periodo=${periodoActual()}`);
    const data = await res.json();
    if (!data.ok) return;

    const r = data.reporte;
    const k = r.kpis;
    document.getElementById('periodoTextoGeneral').textContent = r.periodoTexto;

    const kpis = {
      pacientesTotales: k.pacientesTotales, pacientesNuevos: '+' + k.pacientesNuevos,
      citasCompletadas: k.citasCompletadas, citasProgramadas: k.citasProgramadas,
      tasaAsistencia: k.tasaAsistencia + '%', citasCanceladas: k.citasCanceladas,
      ingresos: money(k.ingresos), margenUtilidad: k.margenUtilidad + '%',
      utilidadNeta: money(k.utilidadNeta), tratamientosActivos: k.tratamientosActivos,
      tratamientosCompletados: k.tratamientosCompletados,
    };
    // Los KPI de la parte superior y del resumen ejecutivo comparten los mismos números,
    // así que se escriben todos juntos con selectores por texto ya presentes en el HTML.
    document.querySelectorAll('#vistaGeneral .kpi-report').forEach((card) => {
      const lbl = card.querySelector('.lbl')?.textContent;
      const val = card.querySelector('.val');
      const sub = card.querySelector('.sub');
      if (lbl === 'Pacientes Totales') { val.textContent = kpis.pacientesTotales; sub.textContent = kpis.pacientesNuevos + ' este mes'; }
      if (lbl === 'Citas Completadas') { val.textContent = kpis.citasCompletadas; sub.textContent = `de ${k.citasProgramadas} programadas`; }
      if (lbl === 'Tasa de Asistencia') { val.textContent = kpis.tasaAsistencia; sub.textContent = `${k.citasCanceladas} cancelaciones`; }
      if (lbl === 'Ingresos del Mes') { val.textContent = kpis.ingresos; sub.textContent = `Margen: ${kpis.margenUtilidad}`; }
      if (lbl === 'Utilidad Neta') { val.textContent = kpis.utilidadNeta; }
      if (lbl === 'Tratamientos Activos') { val.textContent = kpis.tratamientosActivos; sub.textContent = `${k.tratamientosCompletados} completados este mes`; }
    });

    renderResumenEjecutivo(k);
    renderChartsGeneral(r.citasPorMes, r.pacientesPorMes);
  } catch (e) {
    console.error('Error al cargar reporte general:', e);
  }
}

function renderResumenEjecutivo(k) {
  const filas = document.querySelectorAll('#vistaGeneral .card-custom:last-child .d-flex.justify-content-between');
  const valores = [
    k.citasProgramadas, k.citasCompletadas, k.citasCanceladas, k.tasaAsistencia + '%',
    k.pacientesTotales, '+' + k.pacientesNuevos, k.citasCompletadas, k.tratamientosActivos,
    money(k.ingresos), money(k.gastos), money(k.utilidadNeta), k.margenUtilidad + '%',
  ];
  filas.forEach((fila, i) => {
    if (valores[i] !== undefined) fila.querySelector('span:last-child').textContent = valores[i];
  });
}

function renderChartsGeneral(citasPorMes, pacientesPorMes) {
  const meses = citasPorMes.map((m) => m.mes_key.slice(5) + '/' + m.mes_key.slice(2, 4));

  if (chartCitasMes) chartCitasMes.destroy();
  chartCitasMes = new Chart(document.getElementById('chartCitasMes'), {
    type: 'bar',
    data: { labels: meses, datasets: [{ data: citasPorMes.map((m) => Number(m.total)), backgroundColor: '#0d9488', borderRadius: 6, maxBarThickness: 34 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { grid: { display: false } } } },
  });

  if (chartPacientesMes) chartPacientesMes.destroy();
  chartPacientesMes = new Chart(document.getElementById('chartPacientesMes'), {
    type: 'bar',
    data: { labels: pacientesPorMes.map((m) => m.mes_key.slice(5) + '/' + m.mes_key.slice(2, 4)), datasets: [{ data: pacientesPorMes.map((m) => Number(m.total)), backgroundColor: '#2dd4bf', borderRadius: 6, maxBarThickness: 34 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { grid: { display: false } } } },
  });
}

// ---------- FINANCIERO ----------

let chartFinanciero = null;

async function cargarFinanciero() {
  try {
    const res = await fetch(`/api/admin/reportes/financiero.php?periodo=${periodoActual()}`);
    const data = await res.json();
    if (!data.ok) return;

    const r = data.reporte;
    document.getElementById('periodoTextoFinanciero').textContent = r.periodoTexto;

    const cards = document.querySelectorAll('#vistaFinanciero .kpi-report');
    cards[0].querySelector('.val').textContent = money(r.totalIngresos6m);
    cards[0].querySelector('.sub').textContent = 'Promedio mensual: ' + money(r.totalIngresos6m / 6);
    cards[1].querySelector('.val').textContent = money(r.totalGastos6m);
    cards[1].querySelector('.sub').textContent = 'Promedio mensual: ' + money(r.totalGastos6m / 6);
    cards[2].querySelector('.val').textContent = money(r.utilidadAcumulada6m);
    const margenProm = r.totalIngresos6m > 0 ? Math.round((r.utilidadAcumulada6m / r.totalIngresos6m) * 100) : 0;
    cards[2].querySelector('.sub').textContent = 'Margen promedio: ' + margenProm + '%';

    const utilidades = r.detalleMensual.map((m) => m.ingresos - m.gastos);
    if (chartFinanciero) chartFinanciero.destroy();
    chartFinanciero = new Chart(document.getElementById('chartFinanciero'), {
      type: 'bar',
      data: {
        labels: r.detalleMensual.map((m) => m.mes.slice(5) + '/' + m.mes.slice(2, 4)),
        datasets: [
          { label: 'Ingresos', data: r.detalleMensual.map((m) => m.ingresos), backgroundColor: '#0d9488', borderRadius: 6, maxBarThickness: 22 },
          { label: 'Gastos', data: r.detalleMensual.map((m) => m.gastos), backgroundColor: '#f472b6', borderRadius: 6, maxBarThickness: 22 },
          { label: 'Utilidad', data: utilidades, backgroundColor: '#2dd4bf', borderRadius: 6, maxBarThickness: 22 },
        ],
      },
      options: { responsive: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + money(c.parsed.y) } } }, scales: { y: { display: false }, x: { grid: { display: false } } } },
    });

    const coloresMetodo = { efectivo: '#f59e0b', tarjeta: '#0d9488', transferencia: '#2563eb' };
    const nombresMetodo = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', transferencia: 'Transferencia' };
    const totalMetodos = r.metodosPago.reduce((a, m) => a + Number(m.total), 0);

    document.getElementById('metodosPagoContainer').innerHTML = r.metodosPago.map((m) => {
      const pct = totalMetodos > 0 ? Math.round((Number(m.total) / totalMetodos) * 100) : 0;
      return `
        <div class="hbar-row">
          <div class="hbar-label"><span>${nombresMetodo[m.metodo] || m.metodo}</span><span class="fw-semibold">${pct}% <strong>${money(m.total)}</strong></span></div>
          <div class="hbar-track"><div class="hbar-fill" style="width:${pct}%; background:${coloresMetodo[m.metodo] || '#94a3b8'}"></div></div>
        </div>`;
    }).join('') || '<div class="text-muted small">Sin pagos registrados este periodo</div>';

    document.getElementById('detalleMensualBody').innerHTML = r.detalleMensual.map((m) => {
      const uti = m.ingresos - m.gastos;
      const pct = m.ingresos > 0 ? Math.round((uti / m.ingresos) * 100) : 0;
      return `<tr><td>${m.mes}</td><td class="text-success">${money(m.ingresos)}</td><td class="text-danger">${money(m.gastos)}</td><td class="fw-semibold">${money(uti)}</td><td>${pct}%</td></tr>`;
    }).join('') + `<tr class="fw-bold"><td>Total</td><td class="text-success">${money(r.totalIngresos6m)}</td><td class="text-danger">${money(r.totalGastos6m)}</td><td>${money(r.utilidadAcumulada6m)}</td><td>${margenProm}%</td></tr>`;
  } catch (e) {
    console.error('Error al cargar reporte financiero:', e);
  }
}

// ---------- PACIENTES ----------

let chartNuevosPacientes = null, chartDiaSemana = null, chartHoraDia = null;
const COLORES_GENERO = { Femenino: '#f472b6', Masculino: '#0d9488', 'Sin especificar': '#94a3b8', Otro: '#f59e0b' };
const COLORES_EDAD = ['#0d9488', '#2dd4bf', '#f59e0b', '#fb923c', '#f472b6', '#94a3b8'];

async function cargarPacientes() {
  try {
    const res = await fetch('/api/admin/reportes/pacientes.php');
    const data = await res.json();
    if (!data.ok) return;

    const r = data.reporte;
    document.getElementById('pacTasaRetencion').textContent = r.tasaRetencion + '%';
    document.getElementById('pacVisitasPromedio').textContent = r.visitasPromedio;

    if (chartNuevosPacientes) chartNuevosPacientes.destroy();
    chartNuevosPacientes = new Chart(document.getElementById('chartNuevosPacientes'), {
      type: 'bar',
      data: { labels: r.nuevosPorMes.map((m) => m.mes_key.slice(5) + '/' + m.mes_key.slice(2, 4)), datasets: [{ data: r.nuevosPorMes.map((m) => Number(m.total)), backgroundColor: '#2dd4bf', borderRadius: 6, maxBarThickness: 34 }] },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { grid: { display: false } } } },
    });

    const totalGenero = r.distribucionGenero.reduce((a, g) => a + Number(g.total), 0);
    document.querySelector('.gender-bar').innerHTML = r.distribucionGenero.map((g) => {
      const pct = totalGenero > 0 ? Math.round((Number(g.total) / totalGenero) * 100) : 0;
      return `<div style="width:${pct}%; background:${COLORES_GENERO[g.genero] || '#94a3b8'};"></div>`;
    }).join('');
    document.querySelector('.gender-bar').nextElementSibling.innerHTML = r.distribucionGenero.map((g) =>
      `<span><span class="chart-legend-dot" style="background:${COLORES_GENERO[g.genero] || '#94a3b8'}; width:9px;height:9px;border-radius:50%;display:inline-block;"></span> ${g.genero} ${g.total}</span>`
    ).join('');

    document.getElementById('edadContainer').innerHTML = r.distribucionEdad.map((g, i) => `
      <div class="hbar-row">
        <div class="hbar-label"><span>${g.rango}</span><span class="fw-semibold">${g.total} <span class="text-muted">${g.pct}%</span></span></div>
        <div class="hbar-track"><div class="hbar-fill" style="width:${g.pct}%; background:${COLORES_EDAD[i % COLORES_EDAD.length]}"></div></div>
      </div>
    `).join('');

    if (chartDiaSemana) chartDiaSemana.destroy();
    chartDiaSemana = new Chart(document.getElementById('chartDiaSemana'), {
      type: 'bar',
      data: { labels: r.citasPorDiaSemana.map((d) => d.dia), datasets: [{ data: r.citasPorDiaSemana.map((d) => d.total), backgroundColor: '#2dd4bf', borderRadius: 6, maxBarThickness: 34 }] },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { grid: { display: false } } } },
    });

    if (chartHoraDia) chartHoraDia.destroy();
    chartHoraDia = new Chart(document.getElementById('chartHoraDia'), {
      type: 'bar',
      data: { labels: r.citasPorHoraDia.map((h) => h.hora_num + 'h'), datasets: [{ data: r.citasPorHoraDia.map((h) => Number(h.total)), backgroundColor: '#f59e0b', borderRadius: 6, maxBarThickness: 24 }] },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { grid: { display: false } } } },
    });
  } catch (e) {
    console.error('Error al cargar reporte de pacientes:', e);
  }
}

// ---------- TRATAMIENTOS ----------

const COLORES_CATEGORIA = { Preventivo: '#0d9488', Restaurativo: '#2dd4bf', Estetico: '#f59e0b', Ortodoncia: '#fb923c', Cirugia: '#f472b6' };

async function cargarTratamientos() {
  try {
    const res = await fetch(`/api/admin/reportes/tratamientos.php?periodo=${periodoActual()}`);
    const data = await res.json();
    if (!data.ok) return;

    const r = data.reporte;
    document.getElementById('periodoTextoTratamientos').textContent = r.periodoTexto;
    document.getElementById('totalTratamientosBadge').textContent = r.totalTratamientos;

    document.getElementById('categoriasKpiContainer').innerHTML = r.categorias.map((c) => `
      <div class="col-6 col-lg-2-4" style="flex:1 0 18%; max-width:20%;">
        <div class="kpi-report">
          <div class="lbl">${c.categoria}</div>
          <div class="val">${c.total}</div>
          <div class="sub">tratamientos</div>
          <div class="hbar-track mt-1 mb-1"><div class="hbar-fill" style="width:100%; background:${COLORES_CATEGORIA[c.categoria] || '#94a3b8'}"></div></div>
        </div>
      </div>
    `).join('') || '<div class="text-muted small">Sin datos este periodo</div>';

    const maxReal = Math.max(...r.ranking.map((t) => Number(t.realizados)), 1);
    document.getElementById('rankingTratamientosContainer').innerHTML = r.ranking.map((t, i) => {
      const color = COLORES_CATEGORIA[t.categoria] || '#94a3b8';
      return `
        <div class="rank-row">
          <div class="rank-num">${i + 1}</div>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-center">
              <div><span class="fw-semibold">${t.nombre}</span><span class="cat-tag-rep" style="background:${color}22; color:${color}">${t.categoria}</span></div>
              <div class="text-end small"><span class="text-muted">${t.realizados} realizados</span> <span class="fw-semibold">${money(t.monto)}</span></div>
            </div>
            <div class="rank-bar-track mt-1"><div class="rank-bar-fill" style="width:${(t.realizados / maxReal) * 100}%; background:${color}"></div></div>
          </div>
        </div>`;
    }).join('') || '<div class="text-muted small">Sin tratamientos realizados este periodo</div>';

    const totalIngCat = r.categorias.reduce((a, c) => a + Number(c.monto), 0);
    document.getElementById('ingresosCategoriaContainer').innerHTML = r.categorias.map((c) => {
      const pct = totalIngCat > 0 ? Math.round((Number(c.monto) / totalIngCat) * 100) : 0;
      const color = COLORES_CATEGORIA[c.categoria] || '#94a3b8';
      return `
        <div class="hbar-row">
          <div class="hbar-label"><span>${c.categoria}</span><span class="fw-semibold">${money(c.monto)} <span class="text-muted">${pct}%</span></span></div>
          <div class="hbar-track"><div class="hbar-fill" style="width:${pct}%; background:${color}"></div></div>
        </div>`;
    }).join('') + `
      <div class="d-flex gap-3 flex-wrap mt-2 small">
        ${r.categorias.map((c) => `<span><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:${COLORES_CATEGORIA[c.categoria] || '#94a3b8'};margin-right:4px;"></span>${c.categoria}</span>`).join('')}
      </div>`;
  } catch (e) {
    console.error('Error al cargar reporte de tratamientos:', e);
  }
}

// ---------- DENTISTAS ----------

const COLORES_DOCTOR = ['#0d9488', '#f472b6', '#f59e0b', '#f43f5e', '#2563eb', '#8b5cf6'];

function getIniciales(nombre) {
  return nombre.trim().split(/\s+/).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
}

async function cargarDentistas() {
  try {
    const res = await fetch(`/api/admin/reportes/dentistas.php?periodo=${periodoActual()}`);
    const data = await res.json();
    if (!data.ok) return;

    const r = data.reporte;
    document.getElementById('denTotalCitas').textContent = r.totalCitasEquipo;
    document.getElementById('denPromedioCitas').textContent = r.dentistas.length ? `Promedio ${Math.round(r.totalCitasEquipo / r.dentistas.length)} por dentista` : '--';
    document.getElementById('denPacientesAtendidos').textContent = r.pacientesAtendidosEquipo;
    document.getElementById('denEntreProfesionales').textContent = `Entre ${r.dentistas.length} profesionales`;
    document.getElementById('denIngresos').textContent = money(r.ingresosEquipo);
    document.getElementById('denPromedioIngresos').textContent = r.dentistas.length ? `Promedio ${money(r.ingresosEquipo / r.dentistas.length)}` : '--';

    document.getElementById('dentistasContainer').innerHTML = r.dentistas.map((d, i) => {
      const color = COLORES_DOCTOR[i % COLORES_DOCTOR.length];
      return `
        <div class="col-md-6">
          <div class="dentist-card">
            <div class="d-flex gap-2 align-items-center">
              <div class="dentist-avatar" style="background:${color}">${getIniciales(d.nombre)}</div>
              <div><div class="fw-bold small">${d.nombre}</div><div class="text-muted small">${d.especialidad || 'General'}</div></div>
            </div>
            <div class="row g-2 mt-2">
              <div class="col-6">
                <div class="dentist-stat-lbl">Citas</div>
                <div class="dentist-stat-val">${d.citasCompletadas}/${d.citasTotal}</div>
                <div class="text-muted small">${d.pctCompletadas}% completadas</div>
              </div>
              <div class="col-6">
                <div class="dentist-stat-lbl">Pacientes</div>
                <div class="dentist-stat-val">${d.pacientesAtendidos}</div>
                <div class="text-muted small">${d.tratamientosActivos} trats. activos</div>
              </div>
              <div class="col-6">
                <div class="dentist-stat-lbl">Ingresos</div>
                <div class="dentist-stat-val" style="color:var(--teal-dark)">${money(d.ingresos)}</div>
                <div class="text-muted small">${d.pctIngresos}% del total</div>
              </div>
              <div class="col-6">
                <div class="dentist-stat-lbl">Top tratamiento</div>
                <div class="fw-semibold small mt-1">${d.topServicio || 'Sin datos'}</div>
              </div>
            </div>
            <div class="d-flex justify-content-between small mt-2 mb-1"><span class="text-muted">Participación en ingresos</span><span class="fw-semibold">${d.pctIngresos}%</span></div>
            <div class="hbar-track"><div class="hbar-fill" style="width:${d.pctIngresos}%; background:${color}"></div></div>
          </div>
        </div>`;
    }).join('') || '<div class="text-muted small">Sin doctores registrados</div>';

    document.getElementById('rankingDentistasBody').innerHTML = r.dentistas.map((d, i) => {
      const color = COLORES_DOCTOR[i % COLORES_DOCTOR.length];
      return `
        <tr>
          <td class="fw-bold">${i + 1}</td>
          <td><div class="d-flex align-items-center gap-2"><div class="dentist-avatar" style="width:28px;height:28px;font-size:0.65rem;background:${color}">${getIniciales(d.nombre)}</div><div><div class="fw-semibold small">${d.nombre}</div><div class="text-muted small">${d.especialidad || 'General'}</div></div></div></td>
          <td>${d.citasTotal}</td>
          <td class="fw-semibold" style="color:var(--teal-dark)">${d.citasCompletadas}</td>
          <td>${d.pacientesAtendidos}</td>
          <td class="fw-semibold">${money(d.ingresos)}</td>
        </tr>`;
    }).join('');
  } catch (e) {
    console.error('Error al cargar reporte de dentistas:', e);
  }
}

// ---------- EXPORTAR ----------

const NOMBRES_TAB = { tabGeneral: 'General', tabFinanciero: 'Financiero', tabPacientes: 'Pacientes', tabTratamientos: 'Tratamientos', tabDentistas: 'Dentistas' };
const TAB_A_PARAM = { tabGeneral: 'general', tabFinanciero: 'financiero', tabPacientes: 'pacientes', tabTratamientos: 'tratamientos', tabDentistas: 'dentistas' };

function tabActivoId() {
  return document.querySelector('.tabs-rep .btn-teal').id;
}

function activarExportar() {
  document.getElementById('btnExportarPDF').addEventListener('click', exportarPDF);
  document.getElementById('btnExportarExcel').addEventListener('click', exportarExcel);
}

// "PDF" = vista imprimible del navegador. El doctor elige "Guardar como PDF"
// en el diálogo de impresión -- no se genera ningún archivo en el servidor.
function exportarPDF() {
  const tabId = tabActivoId();
  document.getElementById('printTabNombre').textContent = NOMBRES_TAB[tabId];
  document.getElementById('printPeriodo').textContent = document.getElementById('selectorPeriodo').selectedOptions[0]?.textContent || '--';
  document.getElementById('printFecha').textContent = new Date().toLocaleDateString('es-MX');
  window.print();
}

// Excel = CSV generado en el servidor, misma data que ya está en pantalla.
function exportarExcel() {
  const tabParam = TAB_A_PARAM[tabActivoId()];
  window.location.href = `/api/admin/reportes/exportar.php?tab=${tabParam}&periodo=${periodoActual()}`;
}