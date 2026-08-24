// ============================================================
// MÓDULO CONSENTIMIENTOS — conectado al backend real.
// ============================================================

function getIniciales(nombre) {
  return nombre.trim().split(/\s+/).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
}
function claseEstado(estado) {
  return { firmado: 'status-firmado', pendiente: 'status-pendiente', rechazado: 'status-rechazado' }[estado] || 'status-pendiente';
}
function textoEstado(estado) {
  return { firmado: 'Firmado', pendiente: 'Pendiente', rechazado: 'Rechazado' }[estado] || estado;
}
function iconoEstado(estado) {
  return { firmado: 'bi-check-circle', rechazado: 'bi-x-circle', pendiente: 'bi-clock' }[estado] || 'bi-clock';
}

let consentimientosCache = [];
let tiposCache = {};
let filtroEstadoActual = '';
let tipoSeleccionado = null;

document.addEventListener('DOMContentLoaded', function () {
  activarFiltros();
  activarModal();
  activarModalFirmar();
  cargarSelectPacientes();
  cargarTipos();
  cargarResumen();
  cargarLista();
});

function activarFiltros() {
  document.getElementById('filtroBusqueda').addEventListener('input', cargarLista);
  document.getElementById('filtroEstadoGroup').addEventListener('click', function (e) {
    const btn = e.target.closest('button[data-estado]');
    if (!btn) return;
    filtroEstadoActual = btn.getAttribute('data-estado');
    [...this.querySelectorAll('button')].forEach((b) => b.classList.replace('btn-teal', 'btn-outline-soft'));
    btn.classList.replace('btn-outline-soft', 'btn-teal');
    cargarLista();
  });
}

// ---------- RESUMEN + LISTA ----------

async function cargarResumen() {
  try {
    const res = await fetch('/api/admin/consentimientos/resumen.php');
    const data = await res.json();
    if (!data.ok) return;

    document.getElementById('kpiTotal').textContent = data.resumen.total;
    document.getElementById('kpiPendientes').textContent = data.resumen.pendientes;
    document.getElementById('kpiFirmados').textContent = data.resumen.firmados;
    document.getElementById('kpiRechazados').textContent = data.resumen.rechazados;
  } catch (e) {
    console.error('Error al cargar resumen de consentimientos:', e);
  }
}

async function cargarLista() {
  const buscar = document.getElementById('filtroBusqueda').value.trim();

  try {
    const params = new URLSearchParams({ buscar, estado: filtroEstadoActual });
    const res = await fetch('/api/admin/consentimientos/listar.php?' + params);
    const data = await res.json();
    if (!data.ok) return;

    consentimientosCache = data.consentimientos;
    const tbody = document.getElementById('tbodyConsentimientos');
    const empty = document.getElementById('consentimientosEmptyState');

    if (consentimientosCache.length === 0) {
      tbody.innerHTML = '';
      empty.classList.remove('d-none');
      return;
    }
    empty.classList.add('d-none');

    tbody.innerHTML = consentimientosCache.map((c) => `
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="avatar-initial-sq">${getIniciales(c.paciente_nombre)}</div>
            <div class="fw-semibold">${c.paciente_nombre}</div>
          </div>
        </td>
        <td>${c.titulo}</td>
        <td>${c.fecha}</td>
        <td><span class="status-badge ${claseEstado(c.estado)}"><i class="bi ${iconoEstado(c.estado)}"></i> ${textoEstado(c.estado)}</span></td>
        <td class="${c.firma ? 'firma-cursiva' : 'firma-vacia'}">${c.firma || 'Sin firma'}</td>
        <td>
          <i class="bi bi-eye action-icon" data-id="${c.id}" data-action="ver" title="Ver documento" style="cursor:pointer;"></i>
          ${c.estado === 'pendiente' ? `
            <button class="btn btn-teal btn-sm ms-1" data-id="${c.id}" data-action="firmar"><i class="bi bi-pen"></i> Firmar</button>
            <button class="btn btn-outline-soft btn-sm ms-1" data-id="${c.id}" data-action="rechazar" title="Rechazar"><i class="bi bi-x-lg"></i></button>
          ` : ''}
        </td>
      </tr>
    `).join('');
  } catch (e) {
    console.error('Error al cargar consentimientos:', e);
  }
}

document.getElementById('tbodyConsentimientos').addEventListener('click', async function (e) {
  const target = e.target.closest('[data-action]');
  if (!target) return;
  const id = Number(target.getAttribute('data-id'));
  const accion = target.getAttribute('data-action');

  if (accion === 'ver') abrirModalVerDocumento(id);
  if (accion === 'firmar') abrirModalFirmar(id);

  if (accion === 'rechazar') {
    if (!confirm('¿Marcar este consentimiento como rechazado?')) return;
    await enviarFirma(id, 'rechazado', null);
  }
});

async function abrirModalVerDocumento(id) {
  try {
    const res = await fetch('/api/admin/consentimientos/detalle.php?id=' + id);
    const data = await res.json();
    if (!data.ok) { alert(data.mensaje || 'No se pudo cargar el documento.'); return; }

    const c = data.consentimiento;
    document.getElementById('verDocTitulo').textContent = c.titulo;
    document.getElementById('verDocMeta').textContent = `${c.paciente_nombre} · ${c.doctor_nombre}`;
    document.getElementById('verDocEstadoBadge').textContent = textoEstado(c.estado);
    document.getElementById('verDocEstadoBadge').className = `status-badge ${claseEstado(c.estado)}`;
    document.getElementById('verDocTexto').textContent = c.texto;
    document.getElementById('verDocFirma').textContent = c.firma ? `Firmado por: ${c.firma}` : '';

    new bootstrap.Modal(document.getElementById('modalVerDocumento')).show();
  } catch (err) {
    alert('Error de conexión al cargar el documento.');
  }
}

function abrirModalFirmar(id) {
  const c = consentimientosCache.find((x) => x.id === id);
  document.getElementById('firmarConsentimientoId').value = id;
  document.getElementById('inputNombreFirma').value = c ? c.paciente_nombre : '';
  new bootstrap.Modal(document.getElementById('modalFirmarConsentimiento')).show();
}

function activarModalFirmar() {
  document.getElementById('btnConfirmarFirma').addEventListener('click', async function () {
    const id = Number(document.getElementById('firmarConsentimientoId').value);
    const nombreFirma = document.getElementById('inputNombreFirma').value.trim();
    if (!nombreFirma) {
      alert('Escribe el nombre completo para firmar.');
      return;
    }
    await enviarFirma(id, 'firmado', nombreFirma);
    document.activeElement.blur();
    bootstrap.Modal.getInstance(document.getElementById('modalFirmarConsentimiento')).hide();
  });
}

// ---------- SELECT DE PACIENTES ----------

async function cargarSelectPacientes() {
  const select = document.getElementById('selectPacienteConsentimiento');
  try {
    const res = await fetch('/api/admin/citas/catalogos/pacientes.php');
    const data = await res.json();
    const pacientes = data.pacientes || data;
    select.innerHTML = '<option value="">Seleccionar paciente...</option>' +
      pacientes.map((p) => `<option value="${p.id}">${p.nombre}</option>`).join('');
  } catch (e) {
    select.innerHTML = '<option value="">Error al cargar pacientes</option>';
    console.error('Error al cargar pacientes:', e);
  }
  select.addEventListener('change', validarBotonGenerar);
}

// ---------- MODAL: GENERAR CONSENTIMIENTO ----------

async function cargarTipos() {
  try {
    const res = await fetch('/api/admin/consentimientos/tipos.php');
    const data = await res.json();
    if (data.ok) tiposCache = data.tipos;
  } catch (e) {
    console.error('Error al cargar catálogo de tipos:', e);
  }
}

function renderTiposGrid() {
  const cont = document.getElementById('tiposConsentimientoGrid');
  cont.innerHTML = Object.entries(tiposCache).map(([key, t]) => `
    <div class="col-md-6">
      <div class="tipo-card ${tipoSeleccionado === key ? 'selected' : ''}" data-tipo="${key}">
        <div class="icon-box"><i class="bi ${t.icon}"></i></div>
        <div>
          <div class="title">${t.titulo}</div>
          <div class="desc">${t.desc}</div>
        </div>
      </div>
    </div>
  `).join('');
}

function validarBotonGenerar() {
  const paciente = document.getElementById('selectPacienteConsentimiento').value;
  document.getElementById('btnGenerarConsentimiento').disabled = !(paciente && tipoSeleccionado);
}

function activarModal() {
  document.getElementById('tiposConsentimientoGrid').addEventListener('click', function (e) {
    const target = e.target.closest('[data-tipo]');
    if (!target) return;
    tipoSeleccionado = target.getAttribute('data-tipo');
    renderTiposGrid();
    document.getElementById('previewDocumento').textContent = tiposCache[tipoSeleccionado].texto;
    validarBotonGenerar();
  });

  document.getElementById('btnGenerarConsentimiento').addEventListener('click', async function () {
    const pacienteId = document.getElementById('selectPacienteConsentimiento').value;
    if (!pacienteId || !tipoSeleccionado) return;

    try {
      const res = await fetch('/api/admin/consentimientos/crear.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pacienteId, tipo: tipoSeleccionado }),
      });
      const data = await res.json();

      if (!data.ok) { alert(data.mensaje || 'No se pudo generar el consentimiento.'); return; }

      bootstrap.Modal.getInstance(document.getElementById('modalConsentimiento')).hide();
      cargarLista();
      cargarResumen();
    } catch (err) {
      alert('Error de conexión al generar el consentimiento.');
    }
  });
}

window.prepararModalConsentimiento = function () {
  document.getElementById('selectPacienteConsentimiento').value = '';
  tipoSeleccionado = null;
  renderTiposGrid();
  document.getElementById('previewDocumento').textContent = 'Selecciona un tipo de consentimiento para ver la vista previa.';
  document.getElementById('btnGenerarConsentimiento').disabled = true;
};

// Corrige un warning de accesibilidad de Bootstrap 5.3: si el foco del
// teclado sigue dentro del modal justo cuando empieza a cerrarse, hay que
// quitárselo antes, o Bootstrap le pone aria-hidden a un elemento enfocado.
document.addEventListener('hide.bs.modal', function (e) {
  if (e.target.contains(document.activeElement)) {
    document.activeElement.blur();
  }
});