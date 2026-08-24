// ============================================================
// MÓDULO CONFIGURACIÓN — conectado al backend real.
// ============================================================

const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

let doctoresCache = [];

document.addEventListener('DOMContentLoaded', function () {
  activarSubnav();
  activarModalDoctor();
  activarFormularioPassword();

  cargarClinica();
  cargarDoctores();
});

// ---------- NAVEGACIÓN ENTRE SECCIONES ----------

function activarSubnav() {
  const secciones = { clinica: 'seccionClinica', usuarios: 'seccionUsuarios', notificaciones: 'seccionNotificaciones', seguridad: 'seccionSeguridad' };
  const cargado = { clinica: true, usuarios: true, notificaciones: false, seguridad: false };

  document.querySelectorAll('.config-subnav-item').forEach((item) => {
    item.addEventListener('click', function () {
      const seccion = this.getAttribute('data-section');

      document.querySelectorAll('.config-subnav-item').forEach((i) => i.classList.remove('active'));
      this.classList.add('active');

      Object.values(secciones).forEach((id) => document.getElementById(id).classList.add('d-none'));
      document.getElementById(secciones[seccion]).classList.remove('d-none');

      if (!cargado[seccion]) {
        cargado[seccion] = true;
        ({ notificaciones: cargarNotificaciones, seguridad: cargarSeguridad })[seccion]?.();
      }
    });
  });
}

// ---------- CLÍNICA ----------

async function cargarClinica() {
  try {
    const res = await fetch('/api/admin/configuracion/clinica/obtener.php');
    const data = await res.json();
    if (!data.ok) return;

    const d = data.clinica.datos;
    document.getElementById('clNombre').value = d.nombre || '';
    document.getElementById('clSlogan').value = d.slogan || '';
    document.getElementById('clTelefono').value = d.telefono_principal || '';
    document.getElementById('clTelefonoEmergencia').value = d.telefono_emergencia || '';
    document.getElementById('clCorreo').value = d.correo || '';
    document.getElementById('clSitioWeb').value = d.sitio_web || '';
    document.getElementById('clDireccion').value = d.direccion || '';
    document.getElementById('clCiudad').value = d.ciudad || '';
    document.getElementById('clEstadoProvincia').value = d.estado_provincia || '';
    document.getElementById('clCodigoPostal').value = d.codigo_postal || '';
    if (d.pais) document.getElementById('clPais').value = d.pais;
    document.getElementById('clRFC').value = d.rfc || '';
    document.getElementById('clRazonSocial').value = d.razon_social || '';
    if (d.moneda) document.getElementById('clMoneda').value = d.moneda;
    document.getElementById('clIVA').value = d.iva_porcentaje ?? '';
    if (d.duracion_cita_default_min) document.getElementById('clDuracionCita').value = d.duracion_cita_default_min;
    if (d.intervalo_citas_min !== null) document.getElementById('clIntervalo').value = d.intervalo_citas_min;
    if (d.anticipacion_max_dias) document.getElementById('clAnticipacion').value = d.anticipacion_max_dias;

    renderHorarios(data.clinica.horarios);
  } catch (e) {
    console.error('Error al cargar la configuración de la clínica:', e);
  }
}

function renderHorarios(horarios) {
  document.getElementById('horariosContainer').innerHTML = horarios.map((h) => `
    <div class="horario-row">
      <span class="dia-label">${DIAS[h.dia_semana - 1]}</span>
      <div class="switch-cfg ${Number(h.activo) ? 'on' : 'off'}" data-dia="${h.dia_semana}" data-action="toggle-dia"><div class="knob"></div></div>
      <input type="time" class="form-control form-control-sm" style="max-width:120px;" data-dia="${h.dia_semana}" data-campo="inicio" value="${h.hora_inicio ? h.hora_inicio.slice(0, 5) : ''}" ${Number(h.activo) ? '' : 'disabled'}>
      <span class="text-muted small">a</span>
      <input type="time" class="form-control form-control-sm" style="max-width:120px;" data-dia="${h.dia_semana}" data-campo="fin" value="${h.hora_fin ? h.hora_fin.slice(0, 5) : ''}" ${Number(h.activo) ? '' : 'disabled'}>
    </div>
  `).join('');

  document.querySelectorAll('#horariosContainer [data-action="toggle-dia"]').forEach((sw) => {
    sw.addEventListener('click', function () {
      this.classList.toggle('on');
      this.classList.toggle('off');
      const activo = this.classList.contains('on');
      const dia = this.getAttribute('data-dia');
      document.querySelectorAll(`#horariosContainer input[data-dia="${dia}"]`).forEach((input) => (input.disabled = !activo));
    });
  });
}

document.getElementById('btnGuardarClinica').addEventListener('click', async function () {
  const payload = {
    nombre: document.getElementById('clNombre').value,
    slogan: document.getElementById('clSlogan').value,
    telefonoPrincipal: document.getElementById('clTelefono').value,
    telefonoEmergencia: document.getElementById('clTelefonoEmergencia').value,
    correo: document.getElementById('clCorreo').value,
    sitioWeb: document.getElementById('clSitioWeb').value,
    direccion: document.getElementById('clDireccion').value,
    ciudad: document.getElementById('clCiudad').value,
    estadoProvincia: document.getElementById('clEstadoProvincia').value,
    codigoPostal: document.getElementById('clCodigoPostal').value,
    pais: document.getElementById('clPais').value,
    rfc: document.getElementById('clRFC').value,
    razonSocial: document.getElementById('clRazonSocial').value,
    moneda: document.getElementById('clMoneda').value,
    ivaPorcentaje: document.getElementById('clIVA').value,
    duracionCitaMin: document.getElementById('clDuracionCita').value,
    intervaloCitasMin: document.getElementById('clIntervalo').value,
    anticipacionMaxDias: document.getElementById('clAnticipacion').value,
  };

  const horarios = [];
  document.querySelectorAll('#horariosContainer [data-action="toggle-dia"]').forEach((sw) => {
    const dia = sw.getAttribute('data-dia');
    horarios.push({
      dia: Number(dia),
      activo: sw.classList.contains('on'),
      inicio: document.querySelector(`#horariosContainer input[data-dia="${dia}"][data-campo="inicio"]`).value,
      fin: document.querySelector(`#horariosContainer input[data-dia="${dia}"][data-campo="fin"]`).value,
    });
  });

  try {
    const [r1, r2] = await Promise.all([
      fetch('/api/admin/configuracion/clinica/guardar.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }),
      fetch('/api/admin/configuracion/horarios/guardar.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ horarios }) }),
    ]);
    const d1 = await r1.json(), d2 = await r2.json();

    if (!d1.ok || !d2.ok) { alert((d1.mensaje || d2.mensaje) || 'No se pudo guardar.'); return; }
    alert('Configuración de la clínica guardada.');
  } catch (err) {
    alert('Error de conexión al guardar.');
  }
});

// ---------- USUARIOS Y ROLES (doctores) ----------

function getIniciales(nombre) {
  return nombre.trim().split(/\s+/).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
}

async function cargarDoctores() {
  try {
    const res = await fetch('/api/admin/configuracion/doctores/listar.php');
    const data = await res.json();
    if (!data.ok) return;

    doctoresCache = data.doctores;
    renderDoctores(doctoresCache);

    const activos = doctoresCache.filter((d) => Number(d.activo)).length;
    document.getElementById('resumenDoctores').textContent = `${doctoresCache.length} doctores registrados · ${activos} activos`;
  } catch (e) {
    console.error('Error al cargar doctores:', e);
  }
}

function renderDoctores(lista) {
  document.getElementById('doctoresContainer').innerHTML = lista.map((d) => `
    <div class="doctor-row">
      <div class="doctor-avatar" style="background:#0d9488">${getIniciales(d.nombre)}</div>
      <div class="flex-grow-1">
        <div class="fw-semibold small">${d.nombre}</div>
        <div class="text-muted small">${d.correo} ${d.especialidad ? '· ' + d.especialidad : ''}</div>
      </div>
      <span class="role-badge ${Number(d.activo) ? '' : 'inactivo'}">${Number(d.activo) ? 'Activo' : 'Inactivo'}</span>
      <div class="switch-cfg ${d.activo ? 'on' : 'off'}" data-id="${d.id}" data-action="toggle-doctor"><div class="knob"></div></div>
      <i class="bi bi-pencil action-icon" data-id="${d.id}" data-action="editar-doctor" style="cursor:pointer;"></i>
    </div>
  `).join('') || '<div class="text-muted small">No hay doctores registrados</div>';
}

document.getElementById('buscarDoctor').addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  renderDoctores(doctoresCache.filter((d) => d.nombre.toLowerCase().includes(q) || d.correo.toLowerCase().includes(q)));
});

document.getElementById('doctoresContainer').addEventListener('click', async function (e) {
  const toggle = e.target.closest('[data-action="toggle-doctor"]');
  if (toggle) {
    const activo = !toggle.classList.contains('on');
    try {
      const res = await fetch('/api/admin/configuracion/doctores/cambiar-estado.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ doctorId: toggle.getAttribute('data-id'), activo }),
      });
      const data = await res.json();
      if (!data.ok) { alert(data.mensaje || 'No se pudo actualizar.'); return; }
      cargarDoctores();
    } catch (err) {
      alert('Error de conexión.');
    }
    return;
  }

  const editar = e.target.closest('[data-action="editar-doctor"]');
  if (editar) abrirModalDoctor(Number(editar.getAttribute('data-id')));
});

function activarModalDoctor() {
  document.getElementById('switchDoctorActivo').addEventListener('click', function () {
    this.classList.toggle('on');
    this.classList.toggle('off');
  });

  document.getElementById('formDoctor').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const doctorId = fd.get('doctorId');
    const esEdicion = !!doctorId;

    const payload = {
      nombre: `${fd.get('nombre')} ${fd.get('apellido')}`.trim(),
      correo: fd.get('email'),
      especialidad: fd.get('especialidad'),
      consultorio: fd.get('consultorio'),
      activo: document.getElementById('switchDoctorActivo').classList.contains('on'),
    };

    if (esEdicion) {
      payload.doctorId = Number(doctorId);
    } else {
      const password = fd.get('password');
      if (!password || password.length < 6) { alert('La contraseña debe tener al menos 6 caracteres.'); return; }
      payload.contrasena = password;
    }

    try {
      const res = await fetch(`/api/admin/configuracion/doctores/${esEdicion ? 'actualizar' : 'crear'}.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!data.ok) { alert(data.mensaje || 'No se pudo guardar el doctor.'); return; }

      bootstrap.Modal.getInstance(document.getElementById('modalDoctor')).hide();
      cargarDoctores();
    } catch (err) {
      alert('Error de conexión al guardar el doctor.');
    }
  });
}

function abrirModalDoctor(doctorId) {
  const form = document.getElementById('formDoctor');
  form.reset();

  const campoPassword = document.getElementById('campoPasswordDoctor');
  const switchActivo = document.getElementById('switchDoctorActivo');

  if (doctorId) {
    const d = doctoresCache.find((x) => x.id === doctorId);
    if (!d) return;

    document.getElementById('modalDoctorTitulo').textContent = 'Editar Doctor';
    campoPassword.classList.add('d-none');
    form.elements['doctorId'].value = d.id;

    const partes = d.nombre.trim().split(/\s+/);
    form.elements['nombre'].value = partes.slice(0, -1).join(' ') || partes[0];
    form.elements['apellido'].value = partes.length > 1 ? partes[partes.length - 1] : '';
    form.elements['email'].value = d.correo;
    form.elements['especialidad'].value = d.especialidad || '';
    form.elements['consultorio'].value = d.consultorio || '';

    switchActivo.classList.toggle('on', !!Number(d.activo));
    switchActivo.classList.toggle('off', !Number(d.activo));
  } else {
    document.getElementById('modalDoctorTitulo').textContent = 'Nuevo Doctor';
    campoPassword.classList.remove('d-none');
    form.elements['doctorId'].value = '';
    switchActivo.classList.add('on');
    switchActivo.classList.remove('off');
  }

  new bootstrap.Modal(document.getElementById('modalDoctor')).show();
}

window.prepararModalDoctor = function () {
  abrirModalDoctor(null);
};

// ---------- NOTIFICACIONES ----------

async function cargarNotificaciones() {
  try {
    const res = await fetch('/api/admin/configuracion/notificaciones/obtener.php');
    const data = await res.json();
    if (!data.ok) return;

    document.getElementById('tablaNotifPacientes').innerHTML = data.notificaciones.eventos.map((ev) => `
      <div class="alert-interna-row">
        <div><div class="notif-evento">${ev.evento}</div><div class="notif-desc">${ev.descripcion || ''}</div></div>
        <div class="switch-cfg ${Number(ev.app) ? 'on' : 'off'}" data-id="${ev.id}" data-canal="app" data-action="toggle-notif"><div class="knob"></div></div>
      </div>
    `).join('');

    document.getElementById('alertasInternasContainer').innerHTML = data.notificaciones.alertasInternas.map((al) => `
      <div class="alert-interna-row">
        <div><div class="fw-semibold small">${al.nombre}</div><div class="text-muted small">${al.descripcion || ''}</div></div>
        <div class="switch-cfg ${Number(al.activo) ? 'on' : 'off'}" data-id="${al.id}" data-action="toggle-alerta"><div class="knob"></div></div>
      </div>
    `).join('');

    document.querySelectorAll('[data-action="toggle-notif"], [data-action="toggle-alerta"]').forEach((sw) => {
      sw.addEventListener('click', async function () {
        const estabaEncendido = this.classList.contains('on');
        this.classList.toggle('on');
        this.classList.toggle('off');

        const accion = this.getAttribute('data-action');
        const id = Number(this.getAttribute('data-id'));
        const nuevoValor = !estabaEncendido;

        const payload = accion === 'toggle-notif'
          ? { eventos: [{ id, app: nuevoValor }], alertasInternas: [] }
          : { eventos: [], alertasInternas: [{ id, activo: nuevoValor }] };

        try {
          const res = await fetch('/api/admin/configuracion/notificaciones/guardar.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          });
          const data = await res.json();
          if (!data.ok) {
            this.classList.toggle('on');
            this.classList.toggle('off');
            alert(data.mensaje || 'No se pudo guardar el cambio.');
          }
        } catch (err) {
          this.classList.toggle('on');
          this.classList.toggle('off');
          alert('Error de conexión al guardar el cambio.');
        }
      });
    });
  } catch (e) {
    console.error('Error al cargar notificaciones:', e);
  }
}

// ---------- SEGURIDAD ----------

function activarFormularioPassword() {
  document.getElementById('formPassword').addEventListener('submit', async function (e) {
    e.preventDefault();
    const actual = document.getElementById('pwActual').value;
    const nueva = document.getElementById('pwNueva').value;
    const confirmar = document.getElementById('pwConfirmar').value;

    if (nueva !== confirmar) { alert('La nueva contraseña y su confirmación no coinciden.'); return; }

    try {
      const res = await fetch('/api/admin/configuracion/seguridad/cambiar-password.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ actual, nueva }),
      });
      const data = await res.json();
      if (!data.ok) { alert(data.mensaje || 'No se pudo cambiar la contraseña.'); return; }

      alert('Contraseña actualizada.');
      this.reset();
    } catch (err) {
      alert('Error de conexión al cambiar la contraseña.');
    }
  });
}

async function cargarSeguridad() {
  document.getElementById('sesionesContainer').innerHTML = `
    <div class="sesion-row">
      <div class="sesion-dot" style="background:#16a34a"></div>
      <div class="flex-grow-1">
        <div class="fw-semibold small">Esta sesión</div>
        <div class="text-muted small">No llevamos un registro de sesiones múltiples todavía — solo tu acceso actual.</div>
      </div>
      <span class="sesion-badge activa">Activa</span>
    </div>`;

  try {
    const res = await fetch('/api/admin/configuracion/actividad/listar.php');
    const data = await res.json();
    if (!data.ok) return;

    const colorModulo = { finanzas: '#0d9488', inventario: '#f59e0b', recetas: '#2563eb', consentimientos: '#8b5cf6', recordatorios: '#f472b6', configuracion: '#64748b' };

    document.getElementById('actividadContainer').innerHTML = data.resultado.actividad.map((a) => `
      <div class="activity-row">
        <span class="activity-tag" style="background:${colorModulo[a.modulo] || '#94a3b8'}22; color:${colorModulo[a.modulo] || '#64748b'}">${a.modulo}</span>
        <div class="flex-grow-1">
          <div class="small"><span class="fw-semibold">${a.usuario_nombre || 'Sistema'}</span> — ${a.accion}${a.detalle ? ': ' + a.detalle : ''}</div>
          <div class="text-muted small">${a.fecha_creacion}</div>
        </div>
      </div>
    `).join('') || '<div class="text-muted small">Todavía no hay actividad registrada</div>';
  } catch (e) {
    console.error('Error al cargar actividad:', e);
  }
}

// Corrige un warning de accesibilidad de Bootstrap 5.3: si el foco del
// teclado sigue dentro del modal justo cuando empieza a cerrarse, hay que
// quitárselo antes, o Bootstrap le pone aria-hidden a un elemento enfocado.
document.addEventListener('hide.bs.modal', function (e) {
  if (e.target.contains(document.activeElement)) {
    document.activeElement.blur();
  }
});