/**
 * admin-dashboard.js
 * Responsable del tablero de control del doctor: KPIs, gráfica de
 * ingresos, citas del periodo, pagos recientes y servicios más
 * solicitados. Los botones Hoy/Semana/Mes cambian el rango de
 * fechas que se le pide al backend.
 *
 * [BACKEND] Endpoint que consume:
 *   GET /api/admin/dashboard/resumen.php?periodo=hoy|semana|mes
 */

const ETIQUETAS_PERIODO = {
  hoy:    { citas: 'Citas Hoy',        citasCard: 'Citas de Hoy',        ingresos: 'Ingresos del Día'   },
  semana: { citas: 'Citas esta Semana', citasCard: 'Citas de esta Semana', ingresos: 'Ingresos de la Semana' },
  mes:    { citas: 'Citas este Mes',    citasCard: 'Citas de este Mes',    ingresos: 'Ingresos del Mes'    },
};

document.addEventListener('DOMContentLoaded', () => {
  cargarDashboard('hoy');
  activarSelectorPeriodo();
});

function money(n) {
  return '$' + Number(n).toLocaleString('es-MX');
}

function getIniciales(nombre) {
  return nombre.trim().split(/\s+/).slice(0, 2).map(p => p[0].toUpperCase()).join('');
}

function activarSelectorPeriodo() {
  document.querySelectorAll('#selectorPeriodo [data-periodo]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#selectorPeriodo [data-periodo]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      cargarDashboard(btn.dataset.periodo);
    });
  });
}

function cargarDashboard(periodo) {
  const etiquetas = ETIQUETAS_PERIODO[periodo];
  document.getElementById('lblKpiCitas').textContent = etiquetas.citas;
  document.getElementById('lblKpiIngresos').textContent = etiquetas.ingresos;
  document.getElementById('tituloCitasCard').textContent = etiquetas.citasCard;

  fetch(`/api/admin/dashboard/resumen.php?periodo=${periodo}`)
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(data => {
      renderResumen(data.resumen);
      renderIngresosMensuales(data.ingresosMensuales);
      renderCitas(data.citas);
      renderPagosRecientes(data.pagosRecientes);
      renderTratamientosTop(data.tratamientosTop);
    })
    .catch(err => {
      console.error('Error al cargar el tablero:', err);
      const contenedor = document.getElementById('citasHoyContainer');
      if (contenedor) {
        contenedor.innerHTML = `<div class="text-danger small">No se pudo cargar el tablero (${err.message}).</div>`;
      }
    });
}

function renderResumen(data) {
  document.getElementById('kpiCitas').textContent = data.citas;
  document.getElementById('kpiPacientesActivos').textContent = data.pacientesActivos;
  document.getElementById('kpiIngresos').textContent = money(data.ingresos);
  document.getElementById('kpiCitasPendientes').textContent = data.citasPendientes;
}

function renderIngresosMensuales(data) {
  const total = data.reduce((acc, m) => acc + m.total, 0);
  document.getElementById('ingresosTotalTexto').textContent = money(data[data.length - 1]?.total || total);

  const ctx = document.getElementById('ingresosChart');

  // Si ya existe una gráfica dibujada (de una carga anterior por el
  // cambio de periodo), hay que destruirla antes de crear otra o
  // Chart.js se queja de "Canvas is already in use".
  if (window._ingresosChartInstance) {
    window._ingresosChartInstance.destroy();
  }

  window._ingresosChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(m => m.mes),
      datasets: [{
        data: data.map(m => m.total),
        backgroundColor: '#0d9488',
        borderRadius: 6,
        maxBarThickness: 42
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (c) => money(c.parsed.y) } }
      },
      scales: {
        y: { display: false },
        x: { grid: { display: false }, ticks: { color: '#8a94a6', font: { size: 11 } } }
      }
    }
  });
}

function renderCitas(lista) {
  const container = document.getElementById('citasHoyContainer');
  const empty = document.getElementById('citasHoyEmpty');
  if (!lista.length) {
    container.innerHTML = '';
    empty.classList.remove('d-none');
    return;
  }
  empty.classList.add('d-none');
  container.innerHTML = lista.map(c => `
    <div class="appt-row">
      <div class="avatar-initial">${getIniciales(c.paciente)}</div>
      <div class="flex-grow-1">
        <div class="appt-name">${c.paciente}</div>
        <div class="appt-sub">${c.tratamiento} &middot; ${c.doctor}</div>
      </div>
      <div class="text-end">
        <div class="fw-semibold small">${c.hora}</div>
        <span class="status-badge ${c.estado === 'Confirmada' ? 'status-confirmada' : 'status-pendiente'}">${c.estado}</span>
      </div>
    </div>
  `).join('');
}

function renderPagosRecientes(lista) {
  const container = document.getElementById('pagosRecientesContainer');
  const empty = document.getElementById('pagosRecientesEmpty');
  if (!lista.length) {
    container.innerHTML = '';
    empty.classList.remove('d-none');
    return;
  }
  empty.classList.add('d-none');
  container.innerHTML = lista.map(p => `
    <div class="payment-row d-flex justify-content-between">
      <div>
        <div class="payment-name">${p.paciente}</div>
        <div class="payment-sub">${p.concepto}</div>
        <div class="payment-sub">${p.fecha}</div>
      </div>
      <div class="text-end">
        <div class="payment-amount">${money(p.monto)}</div>
        <span class="payment-method">${p.metodo}</span>
      </div>
    </div>
  `).join('') + `<div class="text-center mt-2"><a href="finanzas.html?tab=pagos" class="link-teal">Ver historial completo</a></div>`;
}

function renderTratamientosTop(lista) {
  const container = document.getElementById('tratamientosContainer');
  if (!lista.length) {
    container.innerHTML = `<div class="text-muted small">Sin datos de tratamientos este periodo</div>`;
    return;
  }
  const max = Math.max(...lista.map(t => t.total));
  container.innerHTML = lista.map(t => `
    <div class="mb-3">
      <div class="d-flex justify-content-between small mb-1">
        <span class="fw-semibold" style="color:var(--teal-dark)">${t.nombre}</span>
        <span class="fw-semibold">${t.total}</span>
      </div>
      <div class="progress-treat"><div class="bar" style="width:${Math.round((t.total / max) * 100)}%"></div></div>
    </div>
  `).join('');
}