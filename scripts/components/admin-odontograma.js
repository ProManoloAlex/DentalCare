/**
 * admin-odontograma.js
 * Diagrama dental interactivo por paciente. La lógica de dibujar
 * el diagrama, la leyenda y el modal de condición es la misma que
 * ya existía — lo único que cambió es de dónde sale la información
 * (antes un array fijo en memoria, ahora la BD real).
 *
 * [BACKEND] Endpoints que consume:
 *   GET  /api/admin/odontograma/listar-pacientes.php?buscar=
 *   GET  /api/admin/odontograma/obtener.php?pacienteId=X
 *   POST /api/admin/odontograma/guardar.php
 */

const CONDICIONES = {
  sano:       { label: 'Sano',       abbr: '',    bg: '#ffffff', border:'#cbd5e1', color: '#94a3b8', icon: 'bi-circle' },
  caries:     { label: 'Caries',     abbr: 'Car', bg: '#fecaca', border:'#fecaca', color: '#dc2626', icon: 'bi-exclamation-circle' },
  obturado:   { label: 'Obturado',   abbr: 'Obt', bg: '#bbf7d0', border:'#bbf7d0', color: '#16a34a', icon: 'bi-droplet-half' },
  extraido:   { label: 'Extraído',   abbr: '×',   bg: '#e2e8f0', border:'#e2e8f0', color: '#64748b', icon: 'bi-x-circle' },
  implante:   { label: 'Implante',   abbr: 'Imp', bg: '#99f6e4', border:'#99f6e4', color: '#0f766e', icon: 'bi-magnet' },
  corona:     { label: 'Corona',     abbr: 'Cor', bg: '#fef08a', border:'#fef08a', color: '#a16207', icon: 'bi-gem' },
  endodoncia: { label: 'Endodoncia', abbr: 'End', bg: '#fed7aa', border:'#fed7aa', color: '#c2410c', icon: 'bi-heart-pulse' },
  fractura:   { label: 'Fractura',   abbr: 'Fra', bg: '#fbcfe8', border:'#fbcfe8', color: '#be185d', icon: 'bi-scissors' },
};
const ORDEN_CONDICIONES = ['sano','caries','obturado','extraido','implante','corona','endodoncia','fractura'];

let pacientesCache = [];
let pacienteActualId = null;
let dientesActuales = null; // { 1: {condicion, notas}, ..., 32: {...} }
let dientesOriginales = null; // snapshot de cuando se cargó, para detectar cambios sin guardar
let ultimaActualizacionActual = '';
let dienteEnEdicion = null;
let condicionSeleccionadaModal = 'sano';

document.addEventListener('DOMContentLoaded', () => {
  cargarListaPacientes();
  renderLeyenda();
  activarEventos();
});

function getIniciales(nombre) {
  return nombre.trim().split(/\s+/).slice(0, 2).map(p => p[0].toUpperCase()).join('');
}

// ---------- LISTA DE PACIENTES ----------

function cargarListaPacientes() {
  const busqueda = document.getElementById('buscarPacienteOdo').value.trim();
  const params = busqueda ? `?buscar=${encodeURIComponent(busqueda)}` : '';

  fetch(`/api/admin/odontograma/listar-pacientes.php${params}`)
    .then(res => res.json())
    .then(pacientes => {
      pacientesCache = pacientes;
      renderListaPacientes();

      if (pacienteActualId === null && pacientes.length > 0) {
        const idDesdeUrl = new URLSearchParams(window.location.search).get('pacienteId');
        const idValido = idDesdeUrl && pacientes.some(p => p.id === parseInt(idDesdeUrl, 10));
        seleccionarPaciente(idValido ? parseInt(idDesdeUrl, 10) : pacientes[0].id);
      }
    })
    .catch(err => console.error('Error al cargar pacientes:', err));
}

function renderListaPacientes() {
  const cont = document.getElementById('listaPacientesOdo');
  cont.innerHTML = pacientesCache.map(p => `
    <div class="patient-list-item ${p.id === pacienteActualId ? 'active' : ''}" data-id="${p.id}">
      <div class="patient-list-avatar">${getIniciales(p.nombre)}</div>
      <div>
        <div class="fw-semibold small">${p.nombre}</div>
        <div class="text-muted small">Act: ${p.ultimaActualizacion}</div>
      </div>
    </div>
  `).join('');
}

function seleccionarPaciente(id) {
  if (id === pacienteActualId) return;

  if (hayCambiosSinGuardar()) {
    const confirmar = confirm('Tienes cambios sin guardar en este paciente. ¿Descartarlos y cambiar de paciente?');
    if (!confirmar) return;
  }

  pacienteActualId = id;
  renderListaPacientes();
  cargarOdontograma(id);
}

function hayCambiosSinGuardar() {
  if (!dientesActuales || !dientesOriginales) return false;
  return JSON.stringify(dientesActuales) !== JSON.stringify(dientesOriginales);
}

// ---------- LEYENDA ----------

function renderLeyenda() {
  const cont = document.getElementById('leyendaCondiciones');
  cont.innerHTML = ORDEN_CONDICIONES.map(key => {
    const c = CONDICIONES[key];
    return `
      <div class="legend-item-o">
        <div class="legend-icon-o" style="background:${c.bg}; color:${c.color}; border:1px solid ${c.border}"><i class="bi ${c.icon}"></i></div>
        ${c.label}
      </div>
    `;
  }).join('');
}

// ---------- DIAGRAMA DENTAL ----------

function cargarOdontograma(pacienteId) {
  fetch(`/api/admin/odontograma/obtener.php?pacienteId=${pacienteId}`)
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(data => {
      dientesActuales = data.dientes;
      dientesOriginales = JSON.parse(JSON.stringify(data.dientes)); // copia independiente
      ultimaActualizacionActual = data.ultimaActualizacion;
      renderOdontograma();
    })
    .catch(err => {
      console.error('Error al cargar odontograma:', err);
      document.getElementById('odoNombrePaciente').textContent = 'Error al cargar';
    });
}

function crearToothBox(numero, datosDiente) {
  const c = CONDICIONES[datosDiente.condicion];
  return `
    <div class="tooth-col">
      <span class="tooth-num">${numero}</span>
      <div class="tooth-box-o" data-num="${numero}" style="background:${c.bg}; border-color:${c.border}; color:${c.color}" title="Diente #${numero} - ${c.label}">
        ${c.abbr}
      </div>
    </div>
  `;
}

function renderOdontograma() {
  if (!dientesActuales) return;
  const p = pacientesCache.find(x => x.id === pacienteActualId);
  if (!p) return;

  document.getElementById('odoAvatar').textContent = getIniciales(p.nombre);
  document.getElementById('odoNombrePaciente').textContent = p.nombre;
  document.getElementById('odoUltimaActualizacion').textContent = 'Última actualización: ' + ultimaActualizacionActual;

  const filaSup = [];
  for (let i = 1; i <= 16; i++) filaSup.push(crearToothBox(i, dientesActuales[i]));
  document.getElementById('filaSuperior').innerHTML = filaSup.join('');

  const filaInf = [];
  for (let i = 32; i >= 17; i--) filaInf.push(crearToothBox(i, dientesActuales[i]));
  document.getElementById('filaInferior').innerHTML = filaInf.join('');

  const contadores = { caries: 0, obturado: 0, extraido: 0, implante: 0, corona: 0, endodoncia: 0, fractura: 0 };
  Object.values(dientesActuales).forEach(d => {
    if (contadores.hasOwnProperty(d.condicion)) contadores[d.condicion]++;
  });
  document.getElementById('statCaries').textContent = contadores.caries;
  document.getElementById('statObturado').textContent = contadores.obturado;
  document.getElementById('statExtraido').textContent = contadores.extraido;
  document.getElementById('statImplante').textContent = contadores.implante;

  const badgesCont = document.getElementById('odoBadgesResumen');
  badgesCont.innerHTML = Object.entries(contadores)
    .filter(([, total]) => total > 0)
    .map(([key, total]) => {
      const c = CONDICIONES[key];
      return `<span class="odo-badge" style="background:${c.bg}; color:${c.color}">${total} ${c.label}</span>`;
    }).join('') || '<span class="text-muted small">Sin registros previos</span>';

  const notasCont = document.getElementById('notasClinicasContainer');
  const notasEmpty = document.getElementById('notasClinicasEmpty');
  const conNotas = Object.entries(dientesActuales).filter(([, d]) => d.condicion !== 'sano' && d.notas);

  if (conNotas.length === 0) {
    notasCont.innerHTML = '';
    notasEmpty.classList.remove('d-none');
  } else {
    notasEmpty.classList.add('d-none');
    notasCont.innerHTML = conNotas.map(([num, d]) => {
      const c = CONDICIONES[d.condicion];
      return `
        <div class="col-md-6">
          <div class="nota-card" style="background:${c.bg}20; border:1px solid ${c.border}">
            <span class="fw-bold">Diente #${num}</span>
            <span class="tag" style="background:${c.bg}; color:${c.color}">${c.label}</span>
            <div class="text-muted mt-1">${d.notas}</div>
          </div>
        </div>
      `;
    }).join('');
  }
}

// ---------- MODAL: editar diente ----------

function abrirModalDiente(numero) {
  if (!dientesActuales) return;
  dienteEnEdicion = numero;
  const datos = dientesActuales[numero];
  condicionSeleccionadaModal = datos.condicion;

  document.getElementById('modalDienteTitulo').textContent = 'Diente #' + numero;
  document.getElementById('notasDienteInput').value = datos.notas || '';
  document.getElementById('contadorNotas').textContent = (datos.notas || '').length;

  renderCondicionesGrid();
  new bootstrap.Modal(document.getElementById('modalDiente')).show();
}

function renderCondicionesGrid() {
  const cont = document.getElementById('condicionesGrid');
  cont.innerHTML = ORDEN_CONDICIONES.map(key => {
    const c = CONDICIONES[key];
    const seleccionado = key === condicionSeleccionadaModal ? 'selected' : '';
    return `
      <div class="col-3">
        <div class="cond-option ${seleccionado}" data-cond="${key}" style="color:${seleccionado ? '' : c.color}">
          <i class="bi ${c.icon}"></i>${c.label}
        </div>
      </div>
    `;
  }).join('');
}

// ---------- Guardado (solo en memoria hasta presionar "Guardar Cambios") ----------

function activarEventos() {
  window.addEventListener('beforeunload', function (e) {
    if (hayCambiosSinGuardar()) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  document.getElementById('condicionesGrid').addEventListener('click', function (e) {
    const target = e.target.closest('[data-cond]');
    if (!target) return;
    condicionSeleccionadaModal = target.getAttribute('data-cond');
    renderCondicionesGrid();
  });

  document.getElementById('notasDienteInput').addEventListener('input', function () {
    document.getElementById('contadorNotas').textContent = this.value.length;
  });

  document.getElementById('btnGuardarDiente').addEventListener('click', function () {
    if (!dientesActuales || dienteEnEdicion === null) return;
    dientesActuales[dienteEnEdicion] = {
      condicion: condicionSeleccionadaModal,
      notas: document.getElementById('notasDienteInput').value.trim(),
    };
    renderOdontograma();
    document.activeElement.blur();
    bootstrap.Modal.getInstance(document.getElementById('modalDiente')).hide();
  });

  ['filaSuperior', 'filaInferior'].forEach(id => {
    document.getElementById(id).addEventListener('click', function (e) {
      const target = e.target.closest('[data-num]');
      if (!target) return;
      abrirModalDiente(parseInt(target.getAttribute('data-num'), 10));
    });
  });

  document.getElementById('listaPacientesOdo').addEventListener('click', function (e) {
    const target = e.target.closest('[data-id]');
    if (!target) return;
    seleccionarPaciente(parseInt(target.getAttribute('data-id'), 10));
  });

  let debounceTimer;
  document.getElementById('buscarPacienteOdo').addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(cargarListaPacientes, 350);
  });

  // Este es el único momento en que se manda algo al backend: al
  // presionar "Guardar Cambios" se envían los 32 dientes completos.
  document.getElementById('btnGuardarCambios').addEventListener('click', function () {
    if (!pacienteActualId || !dientesActuales) return;

    fetch('/api/admin/odontograma/guardar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ pacienteId: pacienteActualId, dientes: dientesActuales }),
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.ok) {
          alert('Cambios guardados correctamente.');
          dientesOriginales = JSON.parse(JSON.stringify(dientesActuales));
          cargarListaPacientes(); // refresca "Última actualización" en la lista
        } else {
          alert(resp.mensaje || 'No se pudo guardar el odontograma.');
        }
      })
      .catch(err => {
        console.error('Error al guardar odontograma:', err);
        alert('Ocurrió un error al guardar los cambios.');
      });
  });
}