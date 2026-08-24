/**
 * admin-citas.js
 * Responsable de la vista Lista de "Gestión de Citas": catálogos
 * del modal (pacientes/doctores/servicios), listado con filtros,
 * y crear/editar/cancelar citas.
 *
 * La vista Calendario se deja pendiente a propósito (solo muestra
 * la cuadrícula vacía por ahora) — se conecta en un paso posterior.
 *
 * [BACKEND] Endpoints que consume:
 *   GET  /api/admin/citas/listar.php?buscar=&estado=&doctor_id=
 *   GET  /api/admin/citas/detalle.php?id=X
 *   POST /api/admin/citas/crear.php
 *   POST /api/admin/citas/actualizar.php
 *   POST /api/admin/citas/cancelar.php
 *   GET  /api/admin/citas/catalogos/{pacientes,doctores,servicios}.php
 */

let serviciosCache = [];
let doctoresCache = [];
let citasCache = [];
let coloresPorDoctor = {};
let horariosPorDia = {}; // { 1: {activo, inicio, fin}, ..., 7: {...} }  -- 1=Lunes...7=Domingo, igual que dia_semana en la BD
let semanaOffsetActual = 0; // 0 = semana de hoy, -1 = semana anterior, +1 = siguiente...
const PALETA_COLORES_DOCTORES = ['#0d9488', '#e11d48', '#16a34a', '#c026d3', '#2563eb', '#ea580c'];

document.addEventListener('DOMContentLoaded', () => {
  cargarCatalogos();
  cargarCitas();
  cargarHorariosAtencion();
  activarFiltros();
  activarToggleVista();
  activarFormularioCita();
  activarAccionesTabla();
  activarAutoDuracion();
  activarValidacionHorario();
  activarNavegacionCalendario();
  dibujarEstructuraCalendario();
});

function getIniciales(nombre) {
  return nombre.trim().split(/\s+/).slice(0, 2).map(p => p[0].toUpperCase()).join('');
}

function formatearFechaCorta(fechaStr) {
  const [y, m, d] = fechaStr.split('-');
  const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  return `${d} ${meses[parseInt(m, 10) - 1]} ${y}`;
}

// Convierte un objeto Date a "YYYY-MM-DD" usando los componentes LOCALES
// (año/mes/día tal como los ve el usuario) -- nunca uses .toISOString()
// para esto, porque convierte a UTC primero y puede recorrer el día
// hacia atrás/adelante según el huso horario de quien esté usando el sistema.
function fechaLocalISO(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const dia = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${dia}`;
}

function estadoClase(estado) {
  return {
    'Confirmada': 'status-confirmada',
    'Pendiente': 'status-pendiente',
    'Completada': 'status-completada',
    'Cancelada': 'status-cancelada',
  }[estado] || 'status-pendiente';
}

// ---------- CATÁLOGOS ----------

function cargarCatalogos() {
  fetch('/api/admin/citas/catalogos/pacientes.php')
    .then(res => res.json())
    .then(pacientes => {
      const select = document.getElementById('selectPacienteModal');
      select.innerHTML = '<option value="">Seleccionar paciente</option>' +
        pacientes.map(p => `<option value="${p.id}">${p.nombre} — ${p.telefono ?? 'sin teléfono'}</option>`).join('');
    })
    .catch(err => console.error('Error al cargar pacientes:', err));

  fetch('/api/admin/citas/catalogos/doctores.php')
    .then(res => res.json())
    .then(doctores => {
      doctoresCache = doctores;
      const opciones = doctores.map(d => `<option value="${d.id}">${d.nombre}${d.especialidad ? ' — ' + d.especialidad : ''}</option>`).join('');

      document.getElementById('selectDoctorModal').innerHTML = '<option value="">Seleccionar odontólogo</option>' + opciones;
      document.getElementById('filtroOdontologo').innerHTML = '<option value="">Todos los odontólogos</option>' +
        doctores.map(d => `<option value="${d.id}">${d.nombre}</option>`).join('');

      renderLeyendaOdontologos(doctores);
      construirColoresPorDoctor(doctores);
    })
    .catch(err => console.error('Error al cargar doctores:', err));

  fetch('/api/admin/citas/catalogos/servicios.php')
    .then(res => res.json())
    .then(servicios => {
      serviciosCache = servicios;
      document.getElementById('selectServicioModal').innerHTML = '<option value="">Seleccionar tratamiento</option>' +
        servicios.map(s => `<option value="${s.id}" data-duracion="${s.duracion_min}">${s.nombre} (${s.duracion_min} min)</option>`).join('');
    })
    .catch(err => console.error('Error al cargar servicios:', err));
}

function construirColoresPorDoctor(doctores) {
  coloresPorDoctor = {};
  doctores.forEach((d, i) => {
    coloresPorDoctor[d.id] = PALETA_COLORES_DOCTORES[i % PALETA_COLORES_DOCTORES.length];
  });
  renderCalendarioEventos(); // por si el calendario ya estaba dibujado antes de que llegaran los colores
}

// Reutiliza el mismo endpoint que llena Configuración → Clínica → Horarios,
// así el calendario siempre refleja lo que el doctor configuró ahí, sin
// necesidad de un endpoint nuevo ni de duplicar esa información.
function cargarHorariosAtencion() {
  fetch('/api/admin/configuracion/clinica/obtener.php')
    .then(res => res.json())
    .then(data => {
      if (!data.ok) return;
      horariosPorDia = {};
      data.clinica.horarios.forEach(h => {
        horariosPorDia[h.dia_semana] = {
          activo: !!Number(h.activo),
          inicio: h.hora_inicio ? h.hora_inicio.slice(0, 5) : null,
          fin: h.hora_fin ? h.hora_fin.slice(0, 5) : null,
        };
      });
      dibujarEstructuraCalendario(); // re-dibuja por si el calendario ya se había pintado sin esta info
      renderCalendarioEventos();
    })
    .catch(err => console.error('Error al cargar horarios de atención:', err));
}

function renderLeyendaOdontologos(doctores) {
  const contenedor = document.getElementById('leyendaOdontologos');
  if (!contenedor) return;

  if (doctores.length === 0) {
    contenedor.innerHTML = '<span class="text-muted small">Sin odontólogos registrados</span>';
    return;
  }

  contenedor.innerHTML = doctores.map((d, i) => {
    const color = PALETA_COLORES_DOCTORES[i % PALETA_COLORES_DOCTORES.length];
    return `<span><span class="cal-legend-dot" style="background:${color}"></span>${d.nombre}</span>`;
  }).join('');
}

function activarAutoDuracion() {
  document.getElementById('selectServicioModal').addEventListener('change', function () {
    const opcion = this.options[this.selectedIndex];
    const duracion = opcion?.dataset?.duracion;
    document.getElementById('duracionInfo').textContent = duracion
      ? `Duración: ${duracion} minutos (según el tratamiento elegido)`
      : '';
  });
}

// ---------- VALIDACIÓN DE HORARIO DE ATENCIÓN EN EL FORMULARIO ----------

// "YYYY-MM-DD" -> 1=Lunes...7=Domingo, igual que dia_semana en la BD.
// Se arma la fecha con año/mes/día sueltos (no parseando el string
// directo) para que no haya corrimiento de un día por zona horaria.
function diaSemanaDeFecha(fechaStr) {
  const [y, m, d] = fechaStr.split('-').map(Number);
  const fecha = new Date(y, m - 1, d);
  const dow = fecha.getDay();
  return dow === 0 ? 7 : dow;
}

function activarValidacionHorario() {
  const form = document.getElementById('formCita');
  form.elements['fecha'].addEventListener('change', validarHorarioFormulario);
  form.elements['hora'].addEventListener('change', validarHorarioFormulario);
}

/** Devuelve true si la combinación fecha+hora actual del formulario es válida (o no hay suficiente info para juzgarla todavía). */
function validarHorarioFormulario() {
  const form = document.getElementById('formCita');
  const fecha = form.elements['fecha'].value;
  const hora = form.elements['hora'].value;
  const aviso = document.getElementById('avisoHorarioCerrado');
  const avisoTexto = document.getElementById('avisoHorarioCerradoTexto');

  if (!fecha) { aviso.classList.add('d-none'); return true; }

  const horarioDia = horariosPorDia[diaSemanaDeFecha(fecha)];
  if (!horarioDia) { aviso.classList.add('d-none'); return true; } // horarios aún no cargados: no bloquear

  if (!horarioDia.activo) {
    avisoTexto.textContent = 'Ese día la clínica está marcada como cerrada en Configuración → Clínica → Horarios.';
    aviso.classList.remove('d-none');
    return false;
  }

  if (hora && horarioDia.inicio && horarioDia.fin && (hora < horarioDia.inicio || hora >= horarioDia.fin)) {
    avisoTexto.textContent = `Ese día el horario de atención es de ${horarioDia.inicio} a ${horarioDia.fin}.`;
    aviso.classList.remove('d-none');
    return false;
  }

  aviso.classList.add('d-none');
  return true;
}

// ---------- LISTADO ----------

function activarFiltros() {
  let debounceTimer;
  document.getElementById('filtroBusqueda').addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(cargarCitas, 350);
  });
  document.getElementById('filtroEstado').addEventListener('change', cargarCitas);
  document.getElementById('filtroOdontologo').addEventListener('change', cargarCitas);
}

function cargarCitas() {
  const buscar = document.getElementById('filtroBusqueda').value.trim();
  const estado = document.getElementById('filtroEstado').value;
  const doctorId = document.getElementById('filtroOdontologo').value;

  const params = new URLSearchParams();
  if (buscar) params.set('buscar', buscar);
  if (estado) params.set('estado', estado);
  if (doctorId) params.set('doctor_id', doctorId);

  fetch(`/api/admin/citas/listar.php?${params.toString()}`)
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(citas => {
      citasCache = citas;
      renderListaCitas(citas);
      renderCalendarioEventos();
    })
    .catch(err => {
      console.error('Error al cargar citas:', err);
      const tbody = document.getElementById('tbodyCitas');
      tbody.innerHTML = `<tr><td colspan="7" class="text-danger small text-center py-3">No se pudieron cargar las citas (${err.message}).</td></tr>`;
    });
}

function renderListaCitas(citas) {
  const tbody = document.getElementById('tbodyCitas');
  const empty = document.getElementById('citasEmptyState');

  if (citas.length === 0) {
    tbody.innerHTML = '';
    empty.classList.remove('d-none');
    return;
  }
  empty.classList.add('d-none');

  tbody.innerHTML = citas.map(c => `
    <tr class="${c.estado === 'Cancelada' ? 'cancelada' : ''}">
      <td>
        <div class="d-flex align-items-center gap-2">
          <div class="avatar-initial-sq">${getIniciales(c.paciente)}</div>
          <div class="fw-semibold">${c.paciente}</div>
        </div>
      </td>
      <td>${formatearFechaCorta(c.fecha)}, ${c.hora}</td>
      <td>${c.tratamiento}${c.tratamientoId ? ' <i class="bi bi-link-45deg" style="color:var(--teal-dark)" title="Parte de un tratamiento de varias sesiones"></i>' : ''}</td>      <td>${c.odontologo}</td>
      <td>${c.duracion} min</td>
      <td><span class="status-badge ${estadoClase(c.estado)}">${c.estado}</span></td>
      <td>
        <i class="bi bi-pencil action-icon" data-id="${c.id}" data-action="editar" title="Editar"></i>
        ${c.estado !== 'Cancelada' ? `<i class="bi bi-x-circle action-icon" data-id="${c.id}" data-action="cancelar" title="Cancelar cita" style="color:#dc2626"></i>` : ''}
      </td>
    </tr>
  `).join('');
}

// ---------- MODAL: crear / editar ----------

window.prepararModalNuevaCita = function () {
  const form = document.getElementById('formCita');
  form.reset();
  form.elements['citaId'].value = '';
  document.getElementById('duracionInfo').textContent = '';
  document.getElementById('avisoHorarioCerrado').classList.add('d-none');
  document.getElementById('modalCitaTitulo').textContent = 'Nueva Cita';
  document.getElementById('modalCitaSubtitulo').textContent = 'Completa la información para agendar';
  document.getElementById('btnGuardarCita').textContent = 'Crear Cita';
};

function abrirEdicion(id) {
  fetch(`/api/admin/citas/detalle.php?id=${id}`)
    .then(res => res.json())
    .then(c => {
      if (!c.ok) {
        alert(c.mensaje || 'No se pudo cargar la cita.');
        return;
      }

      const form = document.getElementById('formCita');
      form.elements['citaId'].value = c.id;
      form.elements['pacienteId'].value = c.pacienteId;
      form.elements['servicioId'].value = c.servicioId;
      form.elements['doctorId'].value = c.doctorId;
      form.elements['fecha'].value = c.fecha;
      form.elements['hora'].value = c.hora;
      form.elements['estado'].value = c.estado;
      form.elements['notas'].value = c.notas || '';

      document.getElementById('modalCitaTitulo').textContent = 'Editar Cita';
      document.getElementById('modalCitaSubtitulo').textContent = 'Actualiza la información de la cita';
      document.getElementById('btnGuardarCita').textContent = 'Guardar Cambios';
      validarHorarioFormulario();

      new bootstrap.Modal(document.getElementById('modalCita')).show();
    })
    .catch(err => {
      console.error('Error al cargar cita para editar:', err);
      alert('Ocurrió un error al cargar la cita.');
    });
}

function cancelarCita(id) {
  if (!confirm('¿Cancelar esta cita?')) return;

  fetch('/api/admin/citas/cancelar.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  })
    .then(res => res.json())
    .then(resp => {
      if (resp.ok) {
        cargarCitas();
      } else {
        alert(resp.mensaje || 'No se pudo cancelar la cita.');
      }
    })
    .catch(err => {
      console.error('Error al cancelar cita:', err);
      alert('Ocurrió un error al cancelar la cita.');
    });
}

function activarFormularioCita() {
  document.getElementById('formCita').addEventListener('submit', function (e) {
    e.preventDefault();

    if (!validarHorarioFormulario()) {
      document.getElementById('avisoHorarioCerrado').scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    const fd = new FormData(this);
    const idExistente = fd.get('citaId');

    const datos = {
      pacienteId: fd.get('pacienteId'),
      doctorId: fd.get('doctorId'),
      servicioId: fd.get('servicioId'),
      fecha: fd.get('fecha'),
      hora: fd.get('hora'),
      estado: fd.get('estado'),
      notas: fd.get('notas'),
    };

    const url = idExistente ? '/api/admin/citas/actualizar.php' : '/api/admin/citas/crear.php';
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
          bootstrap.Modal.getInstance(document.getElementById('modalCita')).hide();
          cargarCitas();
        } else {
          alert(resp.mensaje || 'No se pudo guardar la cita.');
        }
      })
      .catch(err => {
        console.error('Error al guardar cita:', err);
        alert('Ocurrió un error al guardar la cita.');
      });
  });
}

function activarAccionesTabla() {
  document.getElementById('tbodyCitas').addEventListener('click', function (e) {
    const target = e.target.closest('[data-action]');
    if (!target) return;
    const id = target.getAttribute('data-id');
    const accion = target.getAttribute('data-action');
    if (accion === 'editar') abrirEdicion(id);
    if (accion === 'cancelar') cancelarCita(id);
  });
}

// ---------- TOGGLE VISTA (Calendario / Lista) ----------

function activarToggleVista() {
  document.getElementById('btnVistaCalendario').addEventListener('click', () => mostrarVista('calendario'));
  document.getElementById('btnVistaLista').addEventListener('click', () => mostrarVista('lista'));
}

function mostrarVista(vista) {
  const esCalendario = vista === 'calendario';

  document.getElementById('vistaCalendario').classList.toggle('d-none', !esCalendario);
  document.getElementById('vistaLista').classList.toggle('d-none', esCalendario);

  actualizarEstiloBoton('btnVistaCalendario', esCalendario);
  actualizarEstiloBoton('btnVistaLista', !esCalendario);
}

function actualizarEstiloBoton(idBoton, activo) {
  const boton = document.getElementById(idBoton);
  boton.classList.toggle('btn-teal', activo);
  boton.classList.toggle('btn-outline-soft', !activo);
}

// ---------- VISTA CALENDARIO ----------

const HORAS_CALENDARIO = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00'];
const DIAS_CALENDARIO = ['LUN','MAR','MIÉ','JUE','VIE','SÁB','DOM'];
const MESES_CALENDARIO = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

let diasSemanaActual = [];

function activarNavegacionCalendario() {
  document.getElementById('btnSemanaAnterior').addEventListener('click', () => {
    semanaOffsetActual--;
    dibujarEstructuraCalendario();
    renderCalendarioEventos();
  });
  document.getElementById('btnSemanaSiguiente').addEventListener('click', () => {
    semanaOffsetActual++;
    dibujarEstructuraCalendario();
    renderCalendarioEventos();
  });
  document.getElementById('btnHoy').addEventListener('click', (e) => {
    e.preventDefault();
    semanaOffsetActual = 0;
    dibujarEstructuraCalendario();
    renderCalendarioEventos();
  });
}

function dibujarEstructuraCalendario() {
  const hoy = new Date();
  const day = hoy.getDay();
  const diff = (day === 0 ? -6 : 1 - day) + (semanaOffsetActual * 7);
  const lunes = new Date(hoy);
  lunes.setDate(lunes.getDate() + diff);

  diasSemanaActual = [];
  for (let i = 0; i < 7; i++) {
    const d = new Date(lunes);
    d.setDate(d.getDate() + i);
    diasSemanaActual.push(d);
  }

  document.getElementById('calMesTexto').textContent =
    `${MESES_CALENDARIO[diasSemanaActual[0].getMonth()]} de ${diasSemanaActual[0].getFullYear()}`;

  const hoyStr = fechaLocalISO(hoy);
  let html = '<thead><tr><th style="min-width:60px;">Hora</th>';
  diasSemanaActual.forEach(d => {
    const iso = fechaLocalISO(d);
    const esHoy = iso === hoyStr;
    const diaSemanaNum = d.getDay() === 0 ? 7 : d.getDay(); // 1=Lunes...7=Domingo, como en la BD
    const horarioDia = horariosPorDia[diaSemanaNum];
    const cerrado = horarioDia && !horarioDia.activo;

    html += `<th class="${esHoy ? 'today-col' : ''} ${cerrado ? 'dia-cerrado' : ''}">
      ${DIAS_CALENDARIO[d.getDay() === 0 ? 6 : d.getDay() - 1]}<br>${String(d.getDate()).padStart(2, '0')} ${MESES_CALENDARIO[d.getMonth()].slice(0, 3)}
      ${cerrado ? '<div class="cerrado-tag">Cerrado</div>' : ''}
    </th>`;
  });
  html += '</tr></thead><tbody>';
  HORAS_CALENDARIO.forEach((hora, hIdx) => {
    html += `<tr><td class="hour-col">${hora}</td>`;
    for (let dIdx = 0; dIdx < 7; dIdx++) {
      const diaSemanaNum = dIdx + 1; // dIdx 0=Lunes...6=Domingo -> dia_semana 1=Lunes...7=Domingo
      const horarioDia = horariosPorDia[diaSemanaNum];
      let clase = 'cal-slot';

      if (horarioDia && !horarioDia.activo) {
        clase += ' slot-cerrado';
      } else if (horarioDia && horarioDia.activo && horarioDia.inicio && horarioDia.fin) {
        if (hora < horarioDia.inicio || hora >= horarioDia.fin) clase += ' slot-fuera-horario';
      }

      html += `<td id="slot-${dIdx}-${hIdx}" class="${clase}"></td>`;
    }
    html += '</tr>';
  });
  html += '</tbody>';

  document.getElementById('tablaCalendario').innerHTML = html;
}

function renderCalendarioEventos() {
  if (diasSemanaActual.length === 0) return;

  // Limpia eventos previos (por si se está re-dibujando sin redibujar la estructura)
  document.querySelectorAll('.cal-slot').forEach(td => td.innerHTML = '');

  const isoSemana = diasSemanaActual.map(d => fechaLocalISO(d));

  citasCache
    .filter(c => c.estado !== 'Cancelada' && isoSemana.includes(c.fecha))
    .forEach(c => {
      const dIdx = isoSemana.indexOf(c.fecha);
      const horaNum = parseInt(c.hora.split(':')[0], 10);
      const hIdx = HORAS_CALENDARIO.indexOf(String(horaNum).padStart(2, '0') + ':00');
      if (hIdx === -1) return; // fuera del rango visible del calendario

      const slot = document.getElementById(`slot-${dIdx}-${hIdx}`);
      if (!slot) return;

      const color = coloresPorDoctor[c.doctor_id] || '#0d9488';
      const bloque = document.createElement('div');
      bloque.className = 'cal-event';
      bloque.style.background = color + '22';
      bloque.style.borderLeft = `3px solid ${color}`;
      bloque.style.color = color;
      bloque.title = `${c.hora} · ${c.paciente} · ${c.tratamiento} · ${c.odontologo}`;
      bloque.textContent = `${c.hora} ${c.paciente}`;
      bloque.dataset.id = c.id;
      bloque.addEventListener('click', () => abrirEdicion(c.id));

      slot.appendChild(bloque);
    });
}
// Corrige un warning de accesibilidad de Bootstrap 5.3: si el foco del
// teclado sigue dentro del modal justo cuando empieza a cerrarse, hay que
// quitárselo antes, o Bootstrap le pone aria-hidden a un elemento enfocado.
document.addEventListener("hide.bs.modal", function (e) {
  if (e.target.contains(document.activeElement)) {
    document.activeElement.blur();
  }
});