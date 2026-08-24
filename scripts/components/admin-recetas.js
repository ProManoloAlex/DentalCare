// ============================================================
// MÓDULO RECETAS — conectado al backend real.
// ============================================================

function claseEstado(estado) {
  return { activa: 'status-activa', completada: 'status-completada', vencida: 'status-vencida' }[estado] || 'status-activa';
}
function textoEstado(estado) {
  return { activa: 'Activa', completada: 'Completada', vencida: 'Vencida' }[estado] || estado;
}
function formatearFechaCorta(fechaStr) {
  const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  const [y, m, d] = fechaStr.split('-');
  return `${d}-${meses[parseInt(m, 10) - 1]}`;
}
function formatearFechaLarga(fechaStr) {
  const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  const [y, m, d] = fechaStr.split('-');
  return `${parseInt(d, 10)} ${meses[parseInt(m, 10) - 1]} ${y}`;
}

let recetasCache = [];

document.addEventListener('DOMContentLoaded', function () {
  activarTabs();
  activarFiltros();
  activarModalReceta();
  cargarSelectPacientes();

  cargarResumen();
  cargarListaRecetas();
});

// ---------- TABS ----------

function activarTabs() {
  const tabs = { tabResumenRec: 'vistaResumenRec', tabTodasRec: 'vistaTodasRec' };
  Object.keys(tabs).forEach((tabId) => {
    document.getElementById(tabId).addEventListener('click', function () {
      Object.entries(tabs).forEach(([btnId, viewId]) => {
        document.getElementById(viewId).classList.toggle('d-none', btnId !== tabId);
        document.getElementById(btnId).classList.toggle('btn-teal', btnId === tabId);
        document.getElementById(btnId).classList.toggle('btn-outline-soft', btnId !== tabId);
      });
    });
  });
}

function activarFiltros() {
  ['filtroRecBusqueda', 'filtroRecEstado'].forEach((id) => document.getElementById(id).addEventListener('input', cargarListaRecetas));
}

// ---------- RESUMEN ----------

async function cargarResumen() {
  try {
    const res = await fetch('/api/admin/recetas/resumen.php');
    const data = await res.json();
    if (!data.ok) return;

    const r = data.resumen;
    document.getElementById('subtituloRecetas').textContent = `${r.kpis.recetasActivas} recetas activas · ${r.totalRecetas} total`;
    document.getElementById('kpiRecetasActivas').textContent = r.kpis.recetasActivas;
    document.getElementById('kpiEmitidasMes').textContent = r.kpis.emitidasEsteMes;
    document.getElementById('kpiPacientesAtendidos').textContent = r.kpis.pacientesAtendidos;
    document.getElementById('kpiTotalMedicamentos').textContent = r.kpis.totalMedicamentos;

    document.getElementById('recetasRecientesContainer').innerHTML = r.recientesActivas.map((rx) => `
      <div class="receta-item-mini">
        <div class="receta-icon"><i class="bi bi-file-medical"></i></div>
        <div class="flex-grow-1">
          <div class="fw-semibold small">${rx.paciente_nombre}</div>
          <div class="text-muted small">${rx.diagnostico}</div>
        </div>
        <div class="text-end">
          <div class="fw-semibold small" style="color:var(--teal-dark)">${rx.folio}</div>
          <div class="text-muted small">${formatearFechaCorta(rx.fecha)}</div>
        </div>
      </div>
    `).join('') || '<div class="empty-state"><i class="bi bi-file-medical"></i><div>No hay recetas activas</div></div>';

    const estados = [
      { nombre: 'Activas', total: r.porEstado.activa, color: '#16a34a' },
      { nombre: 'Completadas', total: r.porEstado.completada, color: '#2563eb' },
      { nombre: 'Vencidas', total: r.porEstado.vencida, color: '#dc2626' },
    ];
    const maxEstado = Math.max(...estados.map((e) => e.total), 1);
    document.getElementById('estadoRecetasContainer').innerHTML = estados.map((e) => `
      <div class="estado-row-rec">
        <div class="lbl-row"><span><span class="dot-rec" style="background:${e.color}"></span>${e.nombre}</span><span class="fw-semibold">${e.total}</span></div>
        <div class="bar-track-rec"><div class="bar-fill-rec" style="width:${(e.total / maxEstado) * 100}%; background:${e.color}"></div></div>
      </div>
    `).join('');

    document.getElementById('medicamentosTopContainer').innerHTML = r.medicamentosTop.map((m) => `
      <div class="med-chip"><i class="bi bi-capsule text-danger"></i> ${m.nombre}</div>
    `).join('') || '<div class="text-muted small">Sin datos todavía</div>';
  } catch (e) {
    console.error('Error al cargar resumen de recetas:', e);
  }
}

// ---------- TODAS LAS RECETAS ----------

async function cargarListaRecetas() {
  const buscar = document.getElementById('filtroRecBusqueda').value.trim();
  const estado = document.getElementById('filtroRecEstado').value;

  try {
    const params = new URLSearchParams({ buscar, estado });
    const res = await fetch('/api/admin/recetas/listar.php?' + params);
    const data = await res.json();
    if (!data.ok) return;

    recetasCache = data.recetas;
    document.getElementById('listaRecetasContainer').innerHTML = recetasCache.map((r) => `
      <div class="receta-row">
        <div class="receta-icon"><i class="bi bi-file-medical"></i></div>
        <div class="flex-grow-1">
          <span class="fw-semibold">${r.paciente_nombre}</span> <span class="status-badge ${claseEstado(r.estado)}" style="font-size:0.68rem;">${textoEstado(r.estado)}</span>
          <div class="text-muted small">${r.diagnostico}</div>
        </div>
        <div class="text-end">
          <div class="fw-semibold small" style="color:var(--teal-dark)">${r.folio}</div>
          <div class="text-muted small">${formatearFechaLarga(r.fecha)}</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <i class="bi bi-printer action-icon" data-id="${r.id}" data-action="imprimir" title="Imprimir" style="cursor:pointer;"></i>
          <i class="bi bi-pencil action-icon" data-id="${r.id}" data-action="editar" title="Editar" style="cursor:pointer;"></i>
          ${r.estado === 'activa' ? `<i class="bi bi-check-circle action-icon" data-id="${r.id}" data-action="marcar-completada" title="Marcar como completada" style="color:#2563eb; cursor:pointer;"></i>` : ''}
          <i class="bi bi-chevron-down action-icon" data-id="${r.id}" data-action="ver-medicamentos" title="Ver medicamentos" style="cursor:pointer;"></i>
        </div>
      </div>
    `).join('') || '<div class="empty-state"><i class="bi bi-file-medical"></i><div>No hay recetas que coincidan</div></div>';
  } catch (e) {
    console.error('Error al cargar recetas:', e);
  }
}

document.getElementById('listaRecetasContainer').addEventListener('click', async function (e) {
  const imprimirBtn = e.target.closest('[data-action="imprimir"]');
  if (imprimirBtn) {
    imprimirReceta(Number(imprimirBtn.getAttribute('data-id')));
    return;
  }

  const editarBtn = e.target.closest('[data-action="editar"]');
  if (editarBtn) {
    abrirModalReceta(Number(editarBtn.getAttribute('data-id')));
    return;
  }

  const verMeds = e.target.closest('[data-action="ver-medicamentos"]');
  if (verMeds) {
    const r = recetasCache.find((x) => x.id === Number(verMeds.getAttribute('data-id')));
    if (r) abrirModalVerMedicamentos(r);
    return;
  }

  const marcarCompletada = e.target.closest('[data-action="marcar-completada"]');
  if (marcarCompletada) {
    try {
      const res = await fetch('/api/admin/recetas/cambiar-estado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ recetaId: marcarCompletada.getAttribute('data-id'), estado: 'completada' }),
      });
      const data = await res.json();
      if (!data.ok) { alert(data.mensaje || 'No se pudo actualizar la receta.'); return; }
      cargarListaRecetas();
      cargarResumen();
    } catch (err) {
      alert('Error de conexión al actualizar la receta.');
    }
  }
});

// ---------- IMPRIMIR ----------

function mostrarSiExiste(wrapId, spanId, valor, formatear) {
  const wrap = document.getElementById(wrapId);
  if (valor) {
    wrap.classList.remove('d-none');
    document.getElementById(spanId).textContent = formatear ? formatear(valor) : valor;
  } else {
    wrap.classList.add('d-none');
  }
}

function imprimirReceta(recetaId) {
  const r = recetasCache.find((x) => x.id === recetaId);
  if (!r) return;

  document.getElementById('prFolio').textContent = r.folio;
  document.getElementById('prFecha').textContent = formatearFechaLarga(r.fecha);
  document.getElementById('prPaciente').textContent = r.paciente_nombre;
  document.getElementById('prDoctor').textContent = r.doctor_nombre;
  document.getElementById('prDiagnostico').textContent = r.diagnostico;

  mostrarSiExiste('prMotivoWrap', 'prMotivo', r.motivo_consulta);
  mostrarSiExiste('prIndicacionesWrap', 'prIndicaciones', r.indicaciones);
  mostrarSiExiste('prProximaCitaWrap', 'prProximaCita', r.proxima_cita, formatearFechaLarga);

  document.getElementById('prMedicamentos').innerHTML = r.medicamentos.map((m, i) => `
    <div class="pr-medicamento">
      <div style="font-weight:bold;">${i + 1}. ${m.nombre}${m.concentracion ? ' ' + m.concentracion : ''}</div>
      <div style="font-size:0.85rem;">${m.dosis}${m.frecuencia ? ', ' + m.frecuencia : ''}${m.duracion ? ', por ' + m.duracion : ''}</div>
      ${m.instrucciones ? `<div style="font-size:0.8rem; color:#555;">${m.instrucciones}</div>` : ''}
    </div>
  `).join('');

  window.print();
}

function abrirModalVerMedicamentos(r) {
  document.getElementById('verMedFolio').textContent = r.folio;
  document.getElementById('verMedPaciente').textContent = `${r.paciente_nombre} · ${r.fecha}`;

  const cont = document.getElementById('verMedContainer');
  cont.innerHTML = r.medicamentos.map(m => `
    <div class="med-block">
      <div class="med-block-title">${m.nombre}${m.concentracion ? ' — ' + m.concentracion : ''}</div>
      <div class="small"><strong>Dosis:</strong> ${m.dosis}</div>
      ${m.frecuencia ? `<div class="small"><strong>Frecuencia:</strong> ${m.frecuencia}</div>` : ''}
      ${m.duracion ? `<div class="small"><strong>Duración:</strong> ${m.duracion}</div>` : ''}
      ${m.forma ? `<div class="small"><strong>Forma:</strong> ${m.forma}</div>` : ''}
      ${m.instrucciones ? `<div class="small text-muted mt-1">${m.instrucciones}</div>` : ''}
    </div>
  `).join('') || '<div class="text-muted small">Esta receta no tiene medicamentos registrados.</div>';

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVerMedicamentos')).show();
}

// Red de seguridad: si queda un modal-backdrop huérfano después de cerrar
// (bug conocido de Bootstrap 5), lo limpiamos apenas se confirme que ya
// no hay ningún modal abierto.
document.addEventListener('hidden.bs.modal', function () {
  if (document.querySelectorAll('.modal.show').length === 0) {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
  }
});


// ---------- SELECT DE PACIENTES ----------

async function cargarSelectPacientes() {
  const select = document.getElementById('selectPacienteReceta');
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
}

// ---------- MODAL: NUEVA RECETA (medicamentos dinámicos) ----------

let medContador = 0;

function crearBloqueMedicamento(datos) {
  medContador++;
  const div = document.createElement('div');
  div.className = 'med-block';
  div.innerHTML = `
    <div class="d-flex justify-content-between align-items-center">
      <div class="med-block-title">Medicamento ${medContador}</div>
      ${medContador > 1 ? '<i class="bi bi-trash action-icon" style="color:#dc2626; cursor:pointer;" data-action="quitar-medicamento"></i>' : ''}
    </div>
    <div class="row g-2 mb-2">
      <div class="col-md-8">
        <label class="form-label small">Nombre del medicamento *</label>
        <input type="text" class="form-control form-control-sm med-nombre" placeholder="Ej. Amoxicilina" required>
      </div>
      <div class="col-md-4">
        <label class="form-label small">Concentración</label>
        <input type="text" class="form-control form-control-sm med-concentracion" placeholder="Ej. 500 mg">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-md-3">
        <label class="form-label small">Forma</label>
        <select class="form-select form-select-sm med-forma">
          <option>Tabletas</option><option>Cápsulas</option><option>Jarabe</option><option>Gotas</option><option>Suspensión</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">Dosis *</label>
        <input type="text" class="form-control form-control-sm med-dosis" placeholder="Ej. 1 tableta" required>
      </div>
      <div class="col-md-3">
        <label class="form-label small">Frecuencia</label>
        <select class="form-select form-select-sm med-frecuencia">
          <option>Cada 6 horas</option><option>Cada 8 horas</option><option>Cada 12 horas</option><option>Cada 24 horas</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">Duración</label>
        <select class="form-select form-select-sm med-duracion">
          <option>3 días</option><option>5 días</option><option>7 días</option><option>10 días</option><option>14 días</option>
        </select>
      </div>
    </div>
    <label class="form-label small">Instrucciones especiales</label>
    <input type="text" class="form-control form-control-sm med-instrucciones" placeholder="Ej. Tomar con alimentos, no consumir alcohol...">
  `;
  document.getElementById('medicamentosContainer').appendChild(div);

  if (datos) {
    div.querySelector('.med-nombre').value = datos.nombre || '';
    div.querySelector('.med-concentracion').value = datos.concentracion || '';
    if (datos.forma) div.querySelector('.med-forma').value = datos.forma;
    div.querySelector('.med-dosis').value = datos.dosis || '';
    if (datos.frecuencia) div.querySelector('.med-frecuencia').value = datos.frecuencia;
    if (datos.duracion) div.querySelector('.med-duracion').value = datos.duracion;
    div.querySelector('.med-instrucciones').value = datos.instrucciones || '';
  }
}

function activarModalReceta() {
  document.getElementById('btnAgregarMedicamento').addEventListener('click', function (e) {
    e.preventDefault();
    crearBloqueMedicamento();
  });

  document.getElementById('medicamentosContainer').addEventListener('click', function (e) {
    const target = e.target.closest('[data-action="quitar-medicamento"]');
    if (!target) return;
    target.closest('.med-block').remove();
    document.querySelectorAll('.med-block-title').forEach((el, i) => (el.textContent = 'Medicamento ' + (i + 1)));
  });

  document.getElementById('formReceta').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const recetaId = fd.get('recetaId');

    const medicamentos = [...document.querySelectorAll('.med-block')].map((block) => ({
      nombre: block.querySelector('.med-nombre').value,
      concentracion: block.querySelector('.med-concentracion').value,
      forma: block.querySelector('.med-forma').value,
      dosis: block.querySelector('.med-dosis').value,
      frecuencia: block.querySelector('.med-frecuencia').value,
      duracion: block.querySelector('.med-duracion').value,
      instrucciones: block.querySelector('.med-instrucciones').value,
    })).filter((m) => m.nombre && m.dosis);

    if (medicamentos.length === 0) { alert('Agrega al menos un medicamento.'); return; }

    const payload = {
      pacienteId: fd.get('pacienteId'),
      diagnostico: fd.get('diagnostico'),
      motivoConsulta: fd.get('motivo'),
      indicaciones: fd.get('indicaciones'),
      proximaCita: fd.get('proximaCita') || null,
      fecha: new Date().toISOString().slice(0, 10),
      medicamentos,
    };

    const esEdicion = !!recetaId;
    if (esEdicion) payload.recetaId = Number(recetaId);

    try {
      const res = await fetch(`/api/admin/recetas/${esEdicion ? 'actualizar' : 'crear'}.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!data.ok) { alert(data.mensaje || 'No se pudo guardar la receta.'); return; }

      bootstrap.Modal.getInstance(document.getElementById('modalReceta')).hide();
      cargarListaRecetas();
      cargarResumen();
    } catch (err) {
      alert('Error de conexión al guardar la receta.');
    }
  });
}

// Abre el modal en modo "Nueva" (sin id) o "Editar" (con id, precargado
// desde recetasCache -- ya trae los medicamentos anidados, así que no
// hace falta pedirle nada extra al servidor).
function abrirModalReceta(recetaId) {
  document.getElementById('formReceta').reset();
  document.getElementById('medicamentosContainer').innerHTML = '';
  medContador = 0;

  const form = document.getElementById('formReceta');

  if (recetaId) {
    const r = recetasCache.find((x) => x.id === recetaId);
    if (!r) return;

    document.getElementById('modalRecetaTitulo').innerHTML = '<i class="bi bi-file-medical" style="color:var(--teal-dark)"></i> Editar Receta Médica';
    document.getElementById('btnGuardarReceta').innerHTML = '<i class="bi bi-save"></i> Guardar Cambios';

    form.elements['recetaId'].value = r.id;
    form.elements['diagnostico'].value = r.diagnostico;
    form.elements['motivo'].value = r.motivo_consulta || '';
    form.elements['indicaciones'].value = r.indicaciones || '';
    form.elements['proximaCita'].value = r.proxima_cita || '';

    // El select de pacientes se llena de forma async; si aún no ha
    // cargado, se intenta de nuevo un momento después.
    const fijarPaciente = () => {
      const select = document.getElementById('selectPacienteReceta');
      if (select.options.length > 1) select.value = r.paciente_id ?? '';
      else setTimeout(fijarPaciente, 150);
    };
    fijarPaciente();

    r.medicamentos.forEach((m) => crearBloqueMedicamento(m));
  } else {
    document.getElementById('modalRecetaTitulo').innerHTML = '<i class="bi bi-file-medical" style="color:var(--teal-dark)"></i> Nueva Receta Médica';
    document.getElementById('btnGuardarReceta').innerHTML = '<i class="bi bi-save"></i> Crear Receta';
    form.elements['recetaId'].value = '';
    crearBloqueMedicamento();
  }

  new bootstrap.Modal(document.getElementById('modalReceta')).show();
}

window.prepararModalReceta = function () {
  abrirModalReceta(null);
};

// Corrige un warning de accesibilidad de Bootstrap 5.3: si el foco del
// teclado sigue dentro del modal justo cuando empieza a cerrarse, hay que
// quitárselo antes, o Bootstrap le pone aria-hidden a un elemento enfocado.
document.addEventListener('hide.bs.modal', function (e) {
  if (e.target.contains(document.activeElement)) {
    document.activeElement.blur();
  }
});

