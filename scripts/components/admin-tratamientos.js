/**
 * admin-tratamientos.js
 * Responsable de las 2 vistas de "Gestión de Tratamientos":
 *   - Catálogo de Servicios (tabla `servicios`, CRUD completo)
 *   - Tratamientos de Pacientes (tabla `tratamientos`, listar + asignar)
 *
 * [BACKEND] Endpoints que consume:
 *   GET  /api/admin/tratamientos/catalogo/listar.php?buscar=&categoria=&orden=
 *   GET  /api/admin/tratamientos/catalogo/detalle.php?id=X
 *   POST /api/admin/tratamientos/catalogo/crear.php
 *   POST /api/admin/tratamientos/catalogo/actualizar.php
 *   GET  /api/admin/tratamientos/pacientes/listar.php?buscar=&estado=&orden=
 *   POST /api/admin/tratamientos/pacientes/asignar.php
 *   GET  /api/admin/citas/catalogos/{pacientes,doctores}.php (reutilizados)
 */

let catalogoServiciosCache = [];
let doctoresCache = [];
let tiposConsentimientoCache = {};

// Palabras clave para sugerir automáticamente una plantilla de consentimiento
// según el nombre del tratamiento elegido -- es solo un punto de partida,
// el doctor puede cambiarla o editar el texto libremente antes de asignar.
const PALABRAS_CLAVE_TIPO = {
  extraccion: ['extrac'],
  endodoncia: ['endodoncia', 'conducto'],
  implante: ['implante'],
  ortodoncia: ['ortodoncia', 'brackets', 'alineador'],
  blanqueamiento: ['blanqueamiento'],
  periodontal: ['periodont', 'encía', 'encia'],
};

document.addEventListener('DOMContentLoaded', () => {
  cargarCatalogoServicios();
  cargarTratamientosPacientes();
  cargarCatalogosSelects();
  cargarTiposConsentimiento();
  activarFiltrosCatalogo();
  activarFiltrosTratamientos();
  activarToggleVista();
  activarFormularioServicio();
  activarFormularioAsignar();
  activarFormularioAgendarSesion();
  activarAccionesCatalogo();
});

function money(n) {
  return '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 });
}

function formatearFechaCorta(fechaStr) {
  const [y, m, d] = fechaStr.split('-');
  const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  return `${d} ${meses[parseInt(m, 10) - 1]} ${y}`;
}

// ============================================================
// VISTA: CATÁLOGO DE SERVICIOS
// ============================================================

function activarFiltrosCatalogo() {
  let debounceTimer;
  document.getElementById('filtroServicioBusqueda').addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(cargarCatalogoServicios, 350);
  });
  document.getElementById('filtroServicioCategoria').addEventListener('change', cargarCatalogoServicios);
  document.getElementById('ordenServicio').addEventListener('change', cargarCatalogoServicios);
}

function cargarCatalogoServicios() {
  const buscar = document.getElementById('filtroServicioBusqueda').value.trim();
  const categoria = document.getElementById('filtroServicioCategoria').value;
  const orden = document.getElementById('ordenServicio').value;

  const params = new URLSearchParams();
  if (buscar) params.set('buscar', buscar);
  if (categoria) params.set('categoria', categoria);
  params.set('orden', orden);

  fetch(`/api/admin/tratamientos/catalogo/listar.php?${params.toString()}`)
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(servicios => {
      catalogoServiciosCache = servicios;
      renderCatalogoServicios(servicios);
      poblarSelectTratamientoAsignar(servicios);
    })
    .catch(err => {
      console.error('Error al cargar catálogo:', err);
      document.getElementById('gridServicios').innerHTML =
        `<div class="text-danger small">No se pudo cargar el catálogo (${err.message}).</div>`;
    });
}

function renderCatalogoServicios(servicios) {
  const grid = document.getElementById('gridServicios');
  const empty = document.getElementById('serviciosEmptyState');

  if (servicios.length === 0) {
    grid.innerHTML = '';
    empty.classList.remove('d-none');
    return;
  }
  empty.classList.add('d-none');

  grid.innerHTML = servicios.map(s => `
    <div class="col-md-6 col-lg-4">
      <div class="service-card ${s.activo ? '' : 'service-pausado'}">
        <div class="d-flex justify-content-between align-items-start">
          <span class="cat-badge cat-${s.categoria}">${s.categoria}</span>
          <div>
            <i class="bi ${s.activo ? 'bi-pause-circle' : 'bi-play-circle'} edit-icon-corner me-2" 
               data-action="toggle-servicio" data-id="${s.id}" data-activo="${s.activo}"
               title="${s.activo ? 'Pausar (deja de ofrecerse)' : 'Reactivar'}"></i>
            <i class="bi bi-pencil edit-icon-corner me-2" data-action="editar-servicio" data-id="${s.id}"></i>
            <i class="bi bi-trash edit-icon-corner" data-action="eliminar-servicio" data-id="${s.id}" style="color:#dc2626" title="Eliminar (solo si nunca se ha usado)"></i>
          </div>
        </div>
        <h6 class="mt-2">${s.nombre} ${!s.activo ? '<span class="badge-pausado">Pausado</span>' : ''}</h6>
        <div class="desc">${s.descripcion ?? ''}</div>
        <div class="meta">
          <span class="duration"><i class="bi bi-clock"></i> ${s.duracion} min</span>
          <span class="price">${money(s.precio)}</span>
        </div>
      </div>
    </div>
  `).join('');
}

function activarAccionesCatalogo() {
  document.getElementById('gridServicios').addEventListener('click', function (e) {
    const editar = e.target.closest('[data-action="editar-servicio"]');
    if (editar) {
      abrirEdicionServicio(editar.getAttribute('data-id'));
      return;
    }

    const toggle = e.target.closest('[data-action="toggle-servicio"]');
    if (toggle) {
      const id = toggle.getAttribute('data-id');
      const estaActivo = toggle.getAttribute('data-activo') === 'true';
      const mensaje = estaActivo
        ? '¿Pausar este servicio? Deja de ofrecerse para citas/tratamientos nuevos, pero el historial no se toca.'
        : '¿Reactivar este servicio?';
      if (!confirm(mensaje)) return;

      fetch('/api/admin/tratamientos/catalogo/cambiar-estado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, activo: !estaActivo }),
      })
        .then(res => res.json())
        .then(resp => {
          if (resp.ok) {
            cargarCatalogoServicios();
          } else {
            alert(resp.mensaje || 'No se pudo cambiar el estado.');
          }
        })
        .catch(err => {
          console.error('Error al cambiar estado del servicio:', err);
          alert('Ocurrió un error al cambiar el estado.');
        });
      return;
    }

    const eliminar = e.target.closest('[data-action="eliminar-servicio"]');
    if (eliminar) {
      const id = eliminar.getAttribute('data-id');
      if (!confirm('¿Eliminar este servicio del catálogo? Esta acción no se puede deshacer.')) return;

      fetch('/api/admin/tratamientos/catalogo/eliminar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
      })
        .then(res => res.json())
        .then(resp => {
          if (resp.ok) {
            cargarCatalogoServicios();
          } else {
            alert(resp.mensaje || 'No se pudo eliminar el servicio.');
          }
        })
        .catch(err => {
          console.error('Error al eliminar servicio:', err);
          alert('Ocurrió un error al eliminar el servicio.');
        });
    }
  });
}

window.prepararModalServicio = function () {
  const form = document.getElementById('formServicio');
  form.reset();
  form.elements['servicioId'].value = '';
  document.getElementById('modalServicioTitulo').textContent = 'Nuevo Tratamiento';
  document.getElementById('btnGuardarServicio').textContent = 'Agregar';
};

function abrirEdicionServicio(id) {
  fetch(`/api/admin/tratamientos/catalogo/detalle.php?id=${id}`)
    .then(res => res.json())
    .then(s => {
      if (!s.ok) {
        alert(s.mensaje || 'No se pudo cargar el servicio.');
        return;
      }
      const form = document.getElementById('formServicio');
      form.elements['servicioId'].value = s.id;
      form.elements['nombre'].value = s.nombre;
      form.elements['categoria'].value = s.categoria;
      form.elements['descripcion'].value = s.descripcion ?? '';
      form.elements['duracionMin'].value = s.duracionMin;
      form.elements['precio'].value = s.precio;

      document.getElementById('modalServicioTitulo').textContent = 'Editar Tratamiento';
      document.getElementById('btnGuardarServicio').textContent = 'Guardar Cambios';

      new bootstrap.Modal(document.getElementById('modalServicio')).show();
    })
    .catch(err => {
      console.error('Error al cargar servicio:', err);
      alert('Ocurrió un error al cargar el servicio.');
    });
}

function activarFormularioServicio() {
  document.getElementById('formServicio').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const idExistente = fd.get('servicioId');

    const datos = {
      nombre: fd.get('nombre'),
      categoria: fd.get('categoria'),
      descripcion: fd.get('descripcion'),
      duracionMin: fd.get('duracionMin'),
      precio: fd.get('precio'),
    };

    const url = idExistente ? '/api/admin/tratamientos/catalogo/actualizar.php' : '/api/admin/tratamientos/catalogo/crear.php';
    if (idExistente) datos.id = idExistente;

    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos),
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.ok) {
          document.activeElement.blur();
          bootstrap.Modal.getInstance(document.getElementById('modalServicio')).hide();
          cargarCatalogoServicios();
        } else {
          alert(resp.mensaje || 'No se pudo guardar el servicio.');
        }
      })
      .catch(err => {
        console.error('Error al guardar servicio:', err);
        alert('Ocurrió un error al guardar el servicio.');
      });
  });
}

// ============================================================
// VISTA: TRATAMIENTOS DE PACIENTES
// ============================================================

function activarFiltrosTratamientos() {
  let debounceTimer;
  document.getElementById('filtroTratBusqueda').addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(cargarTratamientosPacientes, 350);
  });
  document.getElementById('filtroTratEstado').addEventListener('change', cargarTratamientosPacientes);
  document.getElementById('ordenTrat').addEventListener('change', cargarTratamientosPacientes);
}

let tratamientosCache = [];

function cargarTratamientosPacientes() {
  const buscar = document.getElementById('filtroTratBusqueda').value.trim();
  const estado = document.getElementById('filtroTratEstado').value;
  const orden = document.getElementById('ordenTrat').value;

  const params = new URLSearchParams();
  if (buscar) params.set('buscar', buscar);
  if (estado) params.set('estado', estado);
  params.set('orden', orden);

  fetch(`/api/admin/tratamientos/pacientes/listar.php?${params.toString()}`)
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(data => {
      renderResumenTratamientos(data.resumen);
      tratamientosCache = data.tratamientos;
      renderTablaTratamientos(data.tratamientos);
    })
    .catch(err => {
      console.error('Error al cargar tratamientos:', err);
      document.getElementById('tbodyTratamientos').innerHTML =
        `<tr><td colspan="8" class="text-danger small text-center py-3">No se pudieron cargar (${err.message}).</td></tr>`;
    });
}

function renderResumenTratamientos(resumen) {
  document.getElementById('kpiTotalTratamientos').textContent = resumen.total;
  document.getElementById('kpiEnProgreso').textContent = resumen.en_progreso;
  document.getElementById('kpiCompletados').textContent = resumen.completados;
  document.getElementById('kpiSaldoPendiente').textContent = money(resumen.saldo_pendiente);
}

function renderTablaTratamientos(lista) {
  const tbody = document.getElementById('tbodyTratamientos');
  const empty = document.getElementById('tratPacientesEmptyState');

  if (lista.length === 0) {
    tbody.innerHTML = '';
    empty.classList.remove('d-none');
    return;
  }
  empty.classList.add('d-none');

  tbody.innerHTML = lista.map(t => {
    const pct = t.sesionesTotal > 0 ? Math.round((t.sesionesHechas / t.sesionesTotal) * 100) : 0;
    const estadoClase = t.estado === 'Completado' ? 'status-completado' : (t.estado === 'Cancelado' ? 'status-cancelado' : 'status-enprogreso');
    const puedeAgendar = t.estado === 'En Progreso' && t.sesionesHechas < t.sesionesTotal;

    return `
      <tr>
        <td><div class="fw-semibold">${t.paciente}</div><div class="text-muted small">${t.folio}</div></td>
        <td><div>${t.tratamiento}</div><div class="text-muted small">${formatearFechaCorta(t.fecha)}</div></td>
        <td>${t.dentista}</td>
        <td>
          <div class="small mb-1">${t.sesionesHechas}/${t.sesionesTotal} sesiones</div>
          <div class="progress-mini"><div class="bar" style="width:${pct}%"></div></div>
        </td>
        <td class="small">
          ${t.proximaCita ? t.proximaCita : (puedeAgendar ? '<span class="text-muted">Sin agendar</span>' : '<span class="text-muted">—</span>')}
        </td>
        <td><span class="status-badge ${estadoClase}">${t.estado}</span></td>
        <td>${money(t.costo)}</td>
        <td class="${parseFloat(t.pendiente) > 0 ? 'text-danger fw-semibold' : 'text-success'}">${money(t.pendiente)}</td>
        <td>
          <a href="#" class="link-teal" data-id="${t.id}" data-action="ver-detalle">Ver detalles</a>
          ${puedeAgendar ? `<i class="bi bi-calendar-plus action-icon ms-2" data-id="${t.id}" data-action="agendar-sesion" title="Agendar siguiente sesión" style="color:var(--teal)"></i>` : ''}
          ${t.estado !== 'Cancelado' ? `<i class="bi bi-x-circle action-icon ms-2" data-id="${t.id}" data-action="cancelar-tratamiento" title="Cancelar tratamiento" style="color:#dc2626"></i>` : ''}
        </td>
      </tr>
    `;
  }).join('');

  tbody.querySelectorAll('[data-action="cancelar-tratamiento"]').forEach(icon => {
    icon.addEventListener('click', () => cancelarTratamiento(icon.getAttribute('data-id')));
  });

  tbody.querySelectorAll('[data-action="agendar-sesion"]').forEach(icon => {
    icon.addEventListener('click', () => abrirModalAgendarSesion(icon.getAttribute('data-id')));
  });

  tbody.querySelectorAll('[data-action="ver-detalle"]').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const t = tratamientosCache.find(x => x.id === parseInt(link.getAttribute('data-id'), 10));
      if (t) abrirModalDetalleTratamiento(t);
    });
  });
}

function abrirModalDetalleTratamiento(t) {
  const pct = t.sesionesTotal > 0 ? Math.round((t.sesionesHechas / t.sesionesTotal) * 100) : 0;
  const estadoClase = t.estado === 'Completado' ? 'status-completado' : (t.estado === 'Cancelado' ? 'status-cancelado' : 'status-enprogreso');

  document.getElementById('detTratPaciente').textContent = t.paciente;
  document.getElementById('detTratNombre').textContent = t.tratamiento;
  document.getElementById('detTratDentista').textContent = t.dentista;
  document.getElementById('detTratEstadoBadge').textContent = t.estado;
  document.getElementById('detTratEstadoBadge').className = `status-badge ${estadoClase}`;
  document.getElementById('detTratProgresoTexto').textContent = `${t.sesionesHechas}/${t.sesionesTotal} sesiones`;
  document.getElementById('detTratProgresoBarra').style.width = pct + '%';
  document.getElementById('detTratCosto').textContent = money(t.costo);
  document.getElementById('detTratPendiente').textContent = money(t.pendiente);
  document.getElementById('detTratProximaCita').textContent = t.proximaCita || 'Sin cita agendada';

  new bootstrap.Modal(document.getElementById('modalDetalleTratamiento')).show();
}

// ---------- Catálogos para el modal Asignar ----------
function cargarCatalogosSelects() {
  fetch('/api/admin/citas/catalogos/pacientes.php')
    .then(res => res.json())
    .then(pacientes => {
      document.getElementById('selectPacienteAsignar').innerHTML = '<option value="">Seleccionar paciente</option>' +
        pacientes.map(p => `<option value="${p.id}">${p.nombre} — ${p.telefono ?? 'sin teléfono'}</option>`).join('');
    })
    .catch(err => console.error('Error al cargar pacientes:', err));

  fetch('/api/admin/citas/catalogos/doctores.php')
    .then(res => res.json())
    .then(doctores => {
      doctoresCache = doctores;
      const opciones = '<option value="">Seleccionar odontólogo</option>' +
        doctores.map(d => `<option value="${d.id}">${d.nombre}</option>`).join('');
      document.getElementById('selectDoctorAsignar').innerHTML = opciones;
      document.getElementById('agendarDoctor').innerHTML = opciones;
    })
    .catch(err => console.error('Error al cargar doctores:', err));
}

function poblarSelectTratamientoAsignar(servicios) {
  const select = document.getElementById('selectTratamientoAsignar');
  const actual = select.value;
  const activos = servicios.filter(s => s.activo);
  select.innerHTML = '<option value="">Seleccionar tratamiento</option>' +
    activos.map(s => `<option value="${s.id}" data-nombre="${s.nombre}" data-categoria="${s.categoria}" data-descripcion="${s.descripcion ?? ''}" data-precio="${s.precio}">${s.nombre} — ${money(s.precio)}</option>`).join('');
  select.value = actual;
}

window.prepararModalAsignar = function () {
  document.getElementById('formAsignar').reset();
  poblarSelectTratamientoAsignar(catalogoServiciosCache);
  document.getElementById('asignarPlantillaConsentimiento').value = 'personalizado';
  document.getElementById('asignarConsentimientoTitulo').value = '';
  document.getElementById('asignarConsentimientoTexto').value = '';
};

// ---------- Consentimiento dentro del modal Asignar ----------

function cargarTiposConsentimiento() {
  fetch('/api/admin/consentimientos/tipos.php')
    .then(res => res.json())
    .then(data => {
      if (!data.ok) return;
      tiposConsentimientoCache = data.tipos;
      poblarSelectPlantillaConsentimiento();
    })
    .catch(err => console.error('Error al cargar tipos de consentimiento:', err));
}

function poblarSelectPlantillaConsentimiento() {
  const select = document.getElementById('asignarPlantillaConsentimiento');
  if (!select) return;
  select.innerHTML = '<option value="personalizado">Personalizado (escribir desde cero)</option>' +
    Object.entries(tiposConsentimientoCache).map(([key, t]) => `<option value="${key}">${t.titulo}</option>`).join('');
}

// Busca coincidencia por palabras clave en el nombre del tratamiento elegido.
// Es solo una sugerencia de partida -- si no encuentra nada, regresa null
// y el formulario cae en "Personalizado" con una plantilla en blanco.
function sugerirTipoPorNombre(nombreTratamiento) {
  const nombre = nombreTratamiento.toLowerCase();
  for (const [tipo, palabras] of Object.entries(PALABRAS_CLAVE_TIPO)) {
    if (palabras.some(p => nombre.includes(p))) return tipo;
  }
  return null;
}

function plantillaPersonalizada(nombreTratamiento) {
  return {
    titulo: `Consentimiento Informado — ${nombreTratamiento}`,
    texto: `CONSENTIMIENTO INFORMADO PARA ${nombreTratamiento.toUpperCase()}\n\n` +
      `El/la paciente declara haber sido informado/a sobre el tratamiento de ${nombreTratamiento}, incluyendo:\n\n` +
      `1. DESCRIPCIÓN DEL PROCEDIMIENTO: [Describe aquí en qué consiste el procedimiento]\n\n` +
      `2. RIESGOS Y COMPLICACIONES POSIBLES: [Describe aquí los riesgos relevantes para este caso]\n\n` +
      `3. ALTERNATIVAS DE TRATAMIENTO: [Describe alternativas si aplican]\n\n` +
      `El/la paciente firma en señal de haber comprendido la información anterior.`,
  };
}

function aplicarPlantillaConsentimiento(tipo, nombreTratamiento) {
  const selectPlantilla = document.getElementById('asignarPlantillaConsentimiento');
  const inputTitulo = document.getElementById('asignarConsentimientoTitulo');
  const textareaTexto = document.getElementById('asignarConsentimientoTexto');

  if (tipo && tiposConsentimientoCache[tipo]) {
    selectPlantilla.value = tipo;
    inputTitulo.value = tiposConsentimientoCache[tipo].titulo;
    textareaTexto.value = tiposConsentimientoCache[tipo].texto;
  } else {
    selectPlantilla.value = 'personalizado';
    const plantilla = plantillaPersonalizada(nombreTratamiento || 'este tratamiento');
    inputTitulo.value = plantilla.titulo;
    textareaTexto.value = plantilla.texto;
  }
}

function activarSelectorPlantillaConsentimiento() {
  document.getElementById('asignarPlantillaConsentimiento').addEventListener('change', function () {
    const opt = document.getElementById('selectTratamientoAsignar').selectedOptions[0];
    const nombreTratamiento = opt?.dataset?.nombre || 'este tratamiento';
    aplicarPlantillaConsentimiento(this.value === 'personalizado' ? null : this.value, nombreTratamiento);
  });
}

function activarFormularioAsignar() {
  document.getElementById('selectTratamientoAsignar').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const precio = opt?.dataset?.precio;
    const costoInput = document.querySelector('#formAsignar [name="costoTotal"]');
    if (precio && !costoInput.value) costoInput.value = precio;

    const nombreTratamiento = opt?.dataset?.nombre || '';
    if (nombreTratamiento) {
      aplicarPlantillaConsentimiento(sugerirTipoPorNombre(nombreTratamiento), nombreTratamiento);
    }
  });

  activarSelectorPlantillaConsentimiento();

  document.getElementById('formAsignar').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const opt = document.getElementById('selectTratamientoAsignar').selectedOptions[0];
    const plantillaElegida = document.getElementById('asignarPlantillaConsentimiento').value;

    const datos = {
      pacienteId: fd.get('pacienteId'),
      doctorId: fd.get('doctorId'),
      servicioId: fd.get('servicioId'),
      nombre: opt?.dataset?.nombre ?? '',
      categoria: opt?.dataset?.categoria ?? '',
      descripcion: opt?.dataset?.descripcion ?? '',
      diagnostico: fd.get('diagnostico'),
      notas: fd.get('notas'),
      sesionesTotal: fd.get('sesionesTotal'),
      fechaInicio: fd.get('fechaInicio'),
      horaInicio: fd.get('horaInicio'),
      costoTotal: fd.get('costoTotal') || 0,
      pagoInicial: fd.get('pagoInicial') || 0,
      consentimientoTipo: plantillaElegida === 'personalizado' ? null : plantillaElegida,
      consentimientoTitulo: document.getElementById('asignarConsentimientoTitulo').value.trim(),
      consentimientoTexto: document.getElementById('asignarConsentimientoTexto').value.trim(),
    };

    if (!datos.consentimientoTitulo || !datos.consentimientoTexto) {
      alert('Completa el consentimiento informado (título y texto) antes de asignar el tratamiento.');
      return;
    }

    fetch('/api/admin/tratamientos/pacientes/asignar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos),
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.ok) {
          document.activeElement.blur();
          bootstrap.Modal.getInstance(document.getElementById('modalAsignar')).hide();
          cargarTratamientosPacientes();
        } else {
          alert(resp.mensaje || 'No se pudo asignar el tratamiento.');
        }
      })
      .catch(err => {
        console.error('Error al asignar tratamiento:', err);
        alert('Ocurrió un error al asignar el tratamiento.');
      });
  });
}

function cancelarTratamiento(id) {
  if (!confirm('¿Cancelar este tratamiento? El historial y los pagos ya registrados se conservan.')) return;

  fetch('/api/admin/tratamientos/pacientes/cancelar.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  })
    .then(res => res.json())
    .then(resp => {
      if (resp.ok) {
        cargarTratamientosPacientes();
      } else {
        alert(resp.mensaje || 'No se pudo cancelar el tratamiento.');
      }
    })
    .catch(err => {
      console.error('Error al cancelar tratamiento:', err);
      alert('Ocurrió un error al cancelar el tratamiento.');
    });
}

// ---------- Toggle de vistas ----------

function activarToggleVista() {
  document.getElementById('btnVistaCatalogo').addEventListener('click', function () {
    document.getElementById('vistaCatalogo').classList.remove('d-none');
    document.getElementById('vistaPacientes').classList.add('d-none');
    this.classList.replace('btn-outline-soft', 'btn-teal');
    document.getElementById('btnVistaPacientes').classList.replace('btn-teal', 'btn-outline-soft');
  });
  document.getElementById('btnVistaPacientes').addEventListener('click', function () {
    document.getElementById('vistaPacientes').classList.remove('d-none');
    document.getElementById('vistaCatalogo').classList.add('d-none');
    this.classList.replace('btn-outline-soft', 'btn-teal');
    document.getElementById('btnVistaCatalogo').classList.replace('btn-teal', 'btn-outline-soft');
  });
}

function abrirModalAgendarSesion(idStr) {
  const id = parseInt(idStr, 10);
  const t = tratamientosCache.find(x => x.id === id);
  if (!t) return;

  if (!t.servicioId) {
    alert('Este tratamiento no tiene ninguna cita previa de la cual tomar el servicio. Contacta soporte.');
    return;
  }

  const form = document.getElementById('formAgendarSesion');
  form.reset();
  form.elements['tratamientoId'].value = t.id;
  form.elements['pacienteId'].value = t.paciente_id;
  form.elements['servicioId'].value = t.servicioId;
  form.elements['doctorId'].value = t.doctorId;

  document.getElementById('agendarSesionSubtitulo').textContent = `${t.paciente} — ${t.tratamiento} (sesión ${t.sesionesHechas + 1} de ${t.sesionesTotal})`;

  new bootstrap.Modal(document.getElementById('modalAgendarSesion')).show();
}

function activarFormularioAgendarSesion() {
  document.getElementById('formAgendarSesion').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const datos = {
      tratamientoId: fd.get('tratamientoId'),
      pacienteId: fd.get('pacienteId'),
      servicioId: fd.get('servicioId'),
      doctorId: fd.get('doctorId'),
      fecha: fd.get('fecha'),
      hora: fd.get('hora'),
      estado: 'pendiente',
      notas: fd.get('notas'),
    };

    fetch('/api/admin/citas/crear.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos),
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.ok) {
          document.activeElement.blur();
          bootstrap.Modal.getInstance(document.getElementById('modalAgendarSesion')).hide();
          cargarTratamientosPacientes();
        } else {
          alert(resp.mensaje || 'No se pudo agendar la sesión.');
        }
      })
      .catch(err => {
        console.error('Error al agendar sesión:', err);
        alert('Ocurrió un error al agendar la sesión.');
      });
  });
}