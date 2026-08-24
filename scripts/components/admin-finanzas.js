// ============================================================
// MÓDULO FINANZAS — conectado al backend real.
// Mismo patrón que admin-citas.js: una función activar*() por
// responsabilidad, todas registradas en DOMContentLoaded.
// ============================================================

function money(n) {
  return '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', function () {
  activarTabs();
  activarTabInicialPorURL();
  activarFiltros();
  activarFormularioPago();
  activarFormularioGasto();
  activarAccionesGastos();
  cargarSelectPacientes();

  cargarResumen();
  cargarFacturas();
  cargarPagos();
  cargarGastos();
});

// ---------- TABS ----------

let mostrarTab; // se asigna dentro de activarTabs(), se usa también en activarTabInicialPorURL()

function activarTabs() {
  const tabs = { tabResumen: 'vistaResumen', tabFacturacion: 'vistaFacturacion', tabPagos: 'vistaPagos', tabGastos: 'vistaGastos' };
  const accionesPorTab = {
    tabResumen: '',
    tabFacturacion: '',
    tabPagos: '',
    tabGastos: '',
  };

  mostrarTab = function (tabId) {
    Object.entries(tabs).forEach(([btnId, viewId]) => {
      document.getElementById(viewId).classList.toggle('d-none', btnId !== tabId);
      document.getElementById(btnId).classList.toggle('btn-teal', btnId === tabId);
      document.getElementById(btnId).classList.toggle('btn-outline-soft', btnId !== tabId);
    });
    document.getElementById('accionesTopContainer').innerHTML =
      accionesPorTab[tabId] + '<button class="btn btn-outline-soft" id="btnExportar"><i class="bi bi-download"></i> Exportar</button>';
  };

  Object.keys(tabs).forEach((tabId) => {
    document.getElementById(tabId).addEventListener('click', () => mostrarTab(tabId));
  });
}

// Permite llegar directo a una pestaña desde otro módulo, ej:
// <a href="finanzas.html?tab=pagos">Ver historial completo</a>
function activarTabInicialPorURL() {
  const tabURL = new URLSearchParams(window.location.search).get('tab');
  const idsValidos = { resumen: 'tabResumen', facturacion: 'tabFacturacion', pagos: 'tabPagos', gastos: 'tabGastos' };

  if (tabURL && idsValidos[tabURL]) {
    mostrarTab(idsValidos[tabURL]);
  }
}

function activarFiltros() {
  ['filtroFacBusqueda', 'filtroFacEstado'].forEach((id) => document.getElementById(id).addEventListener('input', cargarFacturas));
  ['filtroPagBusqueda', 'filtroPagMetodo'].forEach((id) => document.getElementById(id).addEventListener('input', cargarPagos));
  ['filtroGasBusqueda', 'filtroGasCategoria'].forEach((id) => document.getElementById(id).addEventListener('input', cargarGastos));
}

// ---------- RESUMEN ----------

let chartInstance = null;

async function cargarResumen() {
  try {
    const res = await fetch('/api/admin/finanzas/resumen.php');
    const data = await res.json();
    if (!data.ok) return;

    const r = data.resumen;
    document.getElementById('resIngresosMes').textContent = money(r.ingresos_mes);
    document.getElementById('resGastosMes').textContent = money(r.gastos_mes);
    document.getElementById('resUtilidadNeta').textContent = money(r.utilidad_mes);
    document.getElementById('resFacturasEmitidas').textContent = r.total_facturas + ' facturas';
    document.getElementById('resSaldoPendiente').textContent = money(r.saldo_pendiente);

    const totalIngresos6m = r.serie_grafica.reduce((a, m) => a + m.ingresos, 0);
    const totalGastos6m = r.serie_grafica.reduce((a, m) => a + m.gastos, 0);
    document.getElementById('resTotalIngresos6m').textContent = money(totalIngresos6m);
    document.getElementById('resTotalGastos6m').textContent = money(totalGastos6m);
    document.getElementById('resUtilidad6m').textContent = money(totalIngresos6m - totalGastos6m);

    renderChart(r.serie_grafica);
  } catch (e) {
    console.error('Error al cargar resumen de finanzas:', e);
  }
}

function renderChart(serie) {
  const ctx = document.getElementById('chartIngresosGastos');
  if (chartInstance) chartInstance.destroy();
  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: serie.map((m) => m.mes),
      datasets: [
        { label: 'Ingresos', data: serie.map((m) => m.ingresos), backgroundColor: '#0d9488', borderRadius: 4 },
        { label: 'Gastos', data: serie.map((m) => m.gastos), backgroundColor: '#f472b6', borderRadius: 4 },
      ],
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } },
    },
  });
}

// ---------- FACTURACIÓN (derivada, solo lectura) ----------

function claseEstadoFactura(estado) {
  return { pagado: 'status-pagado', parcial: 'status-pagoparcial', pendiente: 'status-pendiente' }[estado] || 'status-pendiente';
}

function textoEstadoFactura(estado) {
  return { pagado: 'Pagado', parcial: 'Pago Parcial', pendiente: 'Pendiente' }[estado] || estado;
}

async function cargarFacturas() {
  const buscar = document.getElementById('filtroFacBusqueda').value.trim();
  const estado = document.getElementById('filtroFacEstado').value;

  try {
    const params = new URLSearchParams({ buscar, estado });
    const res = await fetch('/api/admin/finanzas/facturas/listar.php?' + params);
    const data = await res.json();
    if (!data.ok) return;

    const facturas = data.facturas;
    document.getElementById('facTotalFacturado').textContent = money(facturas.reduce((a, f) => a + Number(f.monto), 0));
    document.getElementById('facCobrado').textContent = money(facturas.reduce((a, f) => a + Math.min(Number(f.monto), Number(f.pagado)), 0));
    document.getElementById('facPendiente').textContent = money(facturas.reduce((a, f) => a + Number(f.saldo), 0));
    document.getElementById('facTotalFacturas').textContent = facturas.length;

    document.getElementById('tbodyFacturas').innerHTML = facturas.map((f) => `
      <tr>
        <td><div class="fw-semibold">${f.folio}</div><div class="text-muted small">${f.paciente}</div></td>
        <td>${f.concepto}</td>
        <td>${f.fecha}</td>
        <td class="fw-semibold">${money(f.monto)}</td>
        <td>${money(f.pagado)}</td>
        <td>${money(f.saldo)}</td>
        <td><span class="status-badge ${claseEstadoFactura(f.estado)}">${textoEstadoFactura(f.estado)}</span></td>
      </tr>
    `).join('') || '<tr><td colspan="7" class="text-center text-muted py-4">No hay facturas registradas</td></tr>';
  } catch (e) {
    console.error('Error al cargar facturas:', e);
  }
}

// ---------- PAGOS ----------

function iconoMetodo(metodo) {
  return { efectivo: 'bi-cash', tarjeta: 'bi-credit-card', transferencia: 'bi-bank' }[metodo] || 'bi-cash';
}

async function cargarPagos() {
  const buscar = document.getElementById('filtroPagBusqueda').value.trim();
  const metodo = document.getElementById('filtroPagMetodo').value.toLowerCase();

  try {
    const params = new URLSearchParams({ buscar, metodo });
    const res = await fetch('/api/admin/finanzas/pagos/listar.php?' + params);
    const data = await res.json();
    if (!data.ok) return;

    const pagos = data.pagos;
    const porMetodo = { efectivo: 0, tarjeta: 0, transferencia: 0 };
    pagos.forEach((p) => { porMetodo[p.metodo] = (porMetodo[p.metodo] || 0) + Number(p.monto); });

    document.getElementById('pagTotalRecibido').textContent = money(pagos.reduce((a, p) => a + Number(p.monto), 0));
    document.getElementById('pagEfectivo').textContent = money(porMetodo.efectivo);
    document.getElementById('pagTarjeta').textContent = money(porMetodo.tarjeta);
    document.getElementById('pagTransferencia').textContent = money(porMetodo.transferencia);
    document.getElementById('pagCantidadMes').textContent = pagos.length;

    document.getElementById('tbodyPagos').innerHTML = pagos.map((p) => `
      <tr>
        <td><div class="fw-semibold">#${p.id}</div><div class="text-muted small">${p.fecha_pago}</div></td>
        <td>${p.paciente_nombre}</td>
        <td>${p.concepto}</td>
        <td>${p.referencia_factura || '—'}</td>
        <td class="fw-semibold">${money(p.monto)}</td>
        <td><span class="metodo-badge"><i class="bi ${iconoMetodo(p.metodo)}"></i> ${p.metodo}</span></td>
      </tr>
    `).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">No hay pagos registrados</td></tr>';
  } catch (e) {
    console.error('Error al cargar pagos:', e);
  }
}

// ---------- GASTOS ----------

function iconoCategoria(categoria) {
  const iconos = { Nomina: 'bi-people', Suministros: 'bi-box-seam', Equipos: 'bi-tools', Renta: 'bi-building', Servicios: 'bi-lightning', Marketing: 'bi-megaphone' };
  return iconos[categoria] || 'bi-tag';
}

function nombreCategoria(categoria) {
  return categoria === 'Nomina' ? 'Nómina' : categoria;
}

async function cargarGastos() {
  const buscar = document.getElementById('filtroGasBusqueda').value.trim();
  const categoria = document.getElementById('filtroGasCategoria').value;

  try {
    const params = new URLSearchParams({ buscar, categoria });
    const res = await fetch('/api/admin/finanzas/gastos/listar.php?' + params);
    const data = await res.json();
    if (!data.ok) return;

    const gastos = data.gastos;
    const total = gastos.reduce((a, g) => a + Number(g.monto), 0);
    const porCategoria = (cat) => gastos.filter((g) => g.categoria === cat).reduce((a, g) => a + Number(g.monto), 0);

    document.getElementById('gasTotal').textContent = money(total);
    document.getElementById('gasNomina').textContent = money(porCategoria('Nomina'));
    document.getElementById('gasSuministros').textContent = money(porCategoria('Suministros'));
    document.getElementById('gasEquipos').textContent = money(porCategoria('Equipos'));
    document.getElementById('gasPendientes').textContent = gastos.filter((g) => g.estado === 'pendiente').length;

    document.getElementById('tbodyGastos').innerHTML = gastos.map((g) => `
      <tr>
        <td><div class="fw-semibold">#${g.id}</div><div class="cat-tag"><i class="bi ${iconoCategoria(g.categoria)}"></i> ${nombreCategoria(g.categoria)}</div></td>
        <td>${g.descripcion}</td>
        <td>${g.proveedor || '—'}</td>
        <td>${g.fecha}</td>
        <td class="text-danger fw-semibold">${money(g.monto)}</td>
        <td>${Number(g.recurrente) ? '<i class="bi bi-arrow-repeat" style="color:var(--teal-dark)" title="Recurrente"></i>' : '<span class="text-muted">-</span>'}</td>
        <td><span class="status-badge ${g.estado === 'pagado' ? 'status-pagado' : 'status-pendiente'}">${g.estado === 'pagado' ? 'Pagado' : 'Pendiente'}</span></td>
        <td>${g.estado === 'pendiente' ? `<i class="bi bi-check-circle action-icon" data-id="${g.id}" data-action="marcar-pagado" title="Marcar como pagado" style="color:#16a34a"></i>` : ''}</td>
      </tr>
    `).join('') || '<tr><td colspan="8" class="text-center text-muted py-4">No hay gastos registrados</td></tr>';
  } catch (e) {
    console.error('Error al cargar gastos:', e);
  }
}

function activarAccionesGastos() {
  document.getElementById('tbodyGastos').addEventListener('click', async function (e) {
    const target = e.target.closest('[data-action="marcar-pagado"]');
    if (!target) return;

    try {
      const res = await fetch('/api/admin/finanzas/gastos/marcar-pagado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ gastoId: target.getAttribute('data-id') }),
      });
      const data = await res.json();
      if (data.ok) {
        cargarGastos();
      } else {
        alert(data.mensaje || 'No se pudo actualizar el gasto.');
      }
    } catch (err) {
      alert('Error de conexión al actualizar el gasto.');
    }
  });
}

// ---------- FORMULARIO: REGISTRAR PAGO ----------

// [BACKEND] Reutiliza el mismo catálogo de pacientes que ya usa Citas.
async function cargarSelectPacientes() {
  const select = document.getElementById('selectPacientePago');
  try {
    const res = await fetch('/api/admin/citas/catalogos/pacientes.php');
    const data = await res.json();
    const pacientes = data.pacientes || data;

    select.innerHTML = '<option value="">Seleccionar paciente</option>' +
      pacientes.map((p) => `<option value="${p.id}">${p.nombre}</option>`).join('');
  } catch (e) {
    select.innerHTML = '<option value="">Error al cargar pacientes</option>';
    console.error('Error al cargar pacientes:', e);
  }

  select.addEventListener('change', cargarSaldosDelPacienteSeleccionado);
}

async function cargarSaldosDelPacienteSeleccionado() {
  const pacienteId = document.getElementById('selectPacientePago').value;
  const selectAplicarA = document.getElementById('selectAplicarA');
  selectAplicarA.innerHTML = '<option value="">Pago libre (no ligado a un tratamiento o cita)</option>';

  if (!pacienteId) return;

  try {
    const res = await fetch('/api/admin/finanzas/pagos/saldos-paciente.php?paciente_id=' + pacienteId);
    const data = await res.json();
    if (!data.ok) return;

    data.saldos.forEach((s) => {
      const opt = document.createElement('option');
      opt.value = `${s.tipo}:${s.id}`;
      opt.textContent = `${s.descripcion} — saldo ${money(s.saldo)}`;
      opt.dataset.saldo = s.saldo;
      selectAplicarA.appendChild(opt);
    });
  } catch (e) {
    console.error('Error al cargar saldos del paciente:', e);
  }
}

function actualizarSaldoDisponible() {
  const select = document.getElementById('selectAplicarA');
  const opt = select.selectedOptions[0];
  const ayuda = document.getElementById('pagoSaldoDisponible');
  const montoInput = document.getElementById('pagoMonto');

  if (opt && opt.dataset.saldo) {
    montoInput.max = opt.dataset.saldo;
    ayuda.textContent = `Saldo disponible: ${money(opt.dataset.saldo)}`;
  } else {
    montoInput.removeAttribute('max');
    ayuda.textContent = '';
  }
  validarMontoPago();
}

function validarMontoPago() {
  const select = document.getElementById('selectAplicarA');
  const opt = select.selectedOptions[0];
  const montoInput = document.getElementById('pagoMonto');
  const error = document.getElementById('pagoErrorMonto');

  const saldo = opt && opt.dataset.saldo ? parseFloat(opt.dataset.saldo) : null;
  const monto = parseFloat(montoInput.value) || 0;

  const excedido = saldo !== null && monto > saldo;
  error.classList.toggle('d-none', !excedido);
  montoInput.classList.toggle('is-invalid', excedido);
  return !excedido;
}

function activarFormularioPago() {
  document.getElementById('selectAplicarA').addEventListener('change', actualizarSaldoDisponible);
  document.getElementById('pagoMonto').addEventListener('input', validarMontoPago);

  document.getElementById('formPago').addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!validarMontoPago()) {
      document.getElementById('pagoMonto').focus();
      return;
    }

    const fd = new FormData(this);

    const [tipoAplicado, idAplicado] = (fd.get('aplicarA') || '').split(':');

    const payload = {
      pacienteId: fd.get('pacienteId'),
      monto: fd.get('monto'),
      metodo: fd.get('metodo'),
      fechaPago: fd.get('fechaPago'),
      tratamientoId: tipoAplicado === 'tratamiento' ? idAplicado : null,
      citaId: tipoAplicado === 'cita' ? idAplicado : null,
    };

    try {
      const res = await fetch('/api/admin/finanzas/pagos/crear.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!data.ok) {
        alert(data.mensaje || 'No se pudo registrar el pago.');
        return;
      }

      this.reset();
      document.getElementById('selectAplicarA').innerHTML = '<option value="">Pago libre (no ligado a un tratamiento o cita)</option>';
      document.getElementById('pagoSaldoDisponible').textContent = '';
      document.getElementById('pagoErrorMonto').classList.add('d-none');
      document.getElementById('pagoMonto').classList.remove('is-invalid');
      document.getElementById('pagoMonto').removeAttribute('max');
      bootstrap.Modal.getInstance(document.getElementById('modalPago')).hide();
      cargarPagos();
      cargarFacturas();
      cargarResumen();
    } catch (err) {
      alert('Error de conexión al registrar el pago.');
    }
  });
}

// ---------- FORMULARIO: REGISTRAR GASTO ----------

function activarFormularioGasto() {
  const form = document.getElementById('formGasto');

  form.querySelector('[name="notas"]').addEventListener('input', function () {
    document.getElementById('contadorNotasGasto').textContent = this.value.length;
  });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);

    const payload = {
      categoria: fd.get('categoria'),
      descripcion: fd.get('descripcion'),
      proveedor: fd.get('proveedor'),
      fecha: fd.get('fecha'),
      monto: fd.get('monto'),
      recurrente: fd.get('recurrente') === 'on',
      notas: fd.get('notas'),
    };

    try {
      const res = await fetch('/api/admin/finanzas/gastos/crear.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!data.ok) {
        alert(data.mensaje || 'No se pudo registrar el gasto.');
        return;
      }

      this.reset();
      document.getElementById('contadorNotasGasto').textContent = '0';
      bootstrap.Modal.getInstance(document.getElementById('modalGasto')).hide();
      cargarGastos();
      cargarResumen();
    } catch (err) {
      alert('Error de conexión al registrar el gasto.');
    }
  });
}