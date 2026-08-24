// ============================================================
// MÓDULO RECORDATORIOS — conectado al backend real.
// Envío: solo Email, automático real vía SMTP (PHPMailer) desde
// EmailService. WhatsApp se dejó fuera por decisión del proyecto
// (evitar costo de la API de Meta o riesgo de un bot no oficial).
// ============================================================

function claseEstado(estado) {
  return { pendiente: 'status-pendiente', enviado: 'status-enviado', fallido: 'status-fallido' }[estado] || 'status-pendiente';
}
function textoEstado(estado) {
  return { pendiente: 'Pendiente', enviado: 'Enviado', fallido: 'Fallido' }[estado] || estado;
}

let programadosCache = [];
let filtroProgramadosEstado = '';

document.addEventListener('DOMContentLoaded', function () {
  activarTabs();
  activarFiltroProgramados();
  activarFiltrosHistorial();
  activarModalRegla();
  activarCanales();

  cargarResumen();
  cargarProgramados();
});

// ---------- TABS ----------

function activarTabs() {
  const tabs = { tabProgramados: 'vistaProgramados', tabReglas: 'vistaReglas', tabHistorial: 'vistaHistorial', tabCanales: 'vistaCanales' };
  const cargado = { tabProgramados: true, tabReglas: false, tabHistorial: false, tabCanales: false };

  Object.keys(tabs).forEach((tabId) => {
    document.getElementById(tabId).addEventListener('click', function () {
      Object.entries(tabs).forEach(([btnId, viewId]) => {
        document.getElementById(viewId).classList.toggle('d-none', btnId !== tabId);
        document.getElementById(btnId).classList.toggle('btn-teal', btnId === tabId);
        document.getElementById(btnId).classList.toggle('btn-outline-soft', btnId !== tabId);
      });
      if (!cargado[tabId]) {
        cargado[tabId] = true;
        ({ tabReglas: cargarReglas, tabHistorial: cargarHistorial, tabCanales: cargarCanales })[tabId]?.();
      }
    });
  });
}

// ---------- RESUMEN ----------

async function cargarResumen() {
  try {
    const res = await fetch('/api/admin/recordatorios/resumen.php');
    const data = await res.json();
    if (!data.ok) return;

    document.getElementById('kpiPendientesHoy').textContent = data.resumen.pendientesHoy;
    document.getElementById('kpiReglasActivas').textContent = data.resumen.reglasActivas;
    document.getElementById('kpiEnviados').textContent = data.resumen.enviados;
    document.getElementById('kpiTasaExito').textContent = data.resumen.tasaExito + '%';
  } catch (e) {
    console.error('Error al cargar resumen de recordatorios:', e);
  }
}

// ---------- PROGRAMADOS ----------

function activarFiltroProgramados() {
  document.getElementById('filtroProgramadosGroup').addEventListener('click', function (e) {
    const btn = e.target.closest('button[data-estado]');
    if (!btn) return;
    filtroProgramadosEstado = btn.getAttribute('data-estado');
    [...this.querySelectorAll('button')].forEach((b) => b.classList.replace('btn-teal', 'btn-outline-soft'));
    btn.classList.replace('btn-outline-soft', 'btn-teal');
    cargarProgramados();
  });
}

async function cargarProgramados() {
  try {
    const params = new URLSearchParams({ estado: filtroProgramadosEstado });
    const res = await fetch('/api/admin/recordatorios/programados/listar.php?' + params);
    const data = await res.json();
    if (!data.ok) return;

    document.getElementById('statPorEnviar').textContent = data.stats.porEnviar;
    document.getElementById('statEnviadosHoy').textContent = data.stats.enviadosHoy;
    document.getElementById('statFallidos').textContent = data.stats.fallidosHoy;
    document.getElementById('badgeProgramados').textContent = data.stats.porEnviar;

    programadosCache = data.programados;
    const tbody = document.getElementById('tbodyProgramados');

    tbody.innerHTML = programadosCache.map((p, i) => `
      <tr>
        <td class="fw-semibold">${p.paciente}</td>
        <td>${p.cita}<div class="text-muted small">${p.fechaCita}</div></td>
        <td><i class="bi bi-envelope"></i> Email</td>
        <td>${p.envio || '—'}</td>
        <td><span class="status-badge ${claseEstado(p.estado)}">${textoEstado(p.estado)}</span></td>
        <td>
          ${p.estado === 'pendiente' && p.correo ? `<button class="btn btn-teal btn-sm" data-idx="${i}" data-action="enviar"><i class="bi bi-envelope"></i> Enviar</button>` : ''}
          ${p.estado === 'pendiente' && !p.correo ? `<span class="text-muted small">Sin correo registrado</span>` : ''}
        </td>
      </tr>
    `).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">No hay recordatorios que coincidan</td></tr>';
  } catch (e) {
    console.error('Error al cargar programados:', e);
  }
}

document.getElementById('tbodyProgramados').addEventListener('click', function (e) {
  const btn = e.target.closest('[data-action="enviar"]');
  if (!btn) return;
  enviarAhora(programadosCache[Number(btn.getAttribute('data-idx'))], btn);
});

// Envía el recordatorio real por SMTP y registra el resultado en un solo
// paso -- ya no abre mailto: ni pide confirmación manual del doctor.
async function enviarAhora(p, btn) {
  const textoOriginal = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';

  try {
    const res = await fetch('/api/admin/recordatorios/programados/enviar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ citaId: p.citaId, reglaId: p.reglaId }),
    });
    const data = await res.json();

    if (!data.ok) {
      alert(data.mensaje || 'El correo no pudo enviarse. Revisa la configuración SMTP.');
    }

    cargarProgramados();
    cargarResumen();
  } catch (err) {
    alert('Error de conexión al enviar el recordatorio.');
    btn.disabled = false;
    btn.innerHTML = textoOriginal;
  }
}

// ---------- REGLAS ----------

let reglasCache = [];

async function cargarReglas() {
  try {
    const res = await fetch('/api/admin/recordatorios/reglas/listar.php');
    const data = await res.json();
    if (!data.ok) return;

    reglasCache = data.reglas;
    document.getElementById('listaReglas').innerHTML = reglasCache.map((r) => `
      <div class="regla-card">
        <div class="d-flex justify-content-between align-items-start">
          <div class="flex-grow-1">
            <div class="fw-semibold">${r.nombre}</div>
            <div class="text-muted small">${r.descripcion || ''}</div>
            <div class="text-muted small mt-1"><i class="bi bi-clock"></i> ${r.horas}h ${r.timing === 'antes' ? 'antes' : 'después'} · <i class="bi bi-envelope"></i> Email · ${textoAplicaA(r.aplica_a)}</div>
          </div>
          <div class="d-flex gap-2 align-items-center">
            <div class="switch-o ${Number(r.activa) ? 'on' : ''}" data-id="${r.id}" data-action="toggle"><div class="knob"></div></div>
            <i class="bi bi-pencil action-icon" data-id="${r.id}" data-action="editar" style="cursor:pointer;"></i>
          </div>
        </div>
      </div>
    `).join('') || '<div class="empty-state"><i class="bi bi-bell"></i><div>No hay reglas creadas</div></div>';
  } catch (e) {
    console.error('Error al cargar reglas:', e);
  }
}

function textoAplicaA(valor) {
  return { todas: 'Todas las citas', confirmadas: 'Solo confirmadas', pendientes: 'Solo pendientes' }[valor] || valor;
}

document.getElementById('listaReglas').addEventListener('click', async function (e) {
  const toggle = e.target.closest('[data-action="toggle"]');
  if (toggle) {
    const activa = !toggle.classList.contains('on');
    try {
      const res = await fetch('/api/admin/recordatorios/reglas/cambiar-estado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reglaId: toggle.getAttribute('data-id'), activa }),
      });
      const data = await res.json();
      if (!data.ok) { alert(data.mensaje || 'No se pudo actualizar la regla.'); return; }
      cargarReglas();
      cargarResumen();
    } catch (err) {
      alert('Error de conexión al actualizar la regla.');
    }
    return;
  }

  const editar = e.target.closest('[data-action="editar"]');
  if (editar) {
    abrirModalRegla(Number(editar.getAttribute('data-id')));
  }
});

function activarModalRegla() {
  document.getElementById('mensajeReglaInput').addEventListener('input', function () {
    document.getElementById('contadorMensajeRegla').textContent = this.value.length;
  });

  document.querySelectorAll('.var-chip').forEach((chip) => {
    chip.addEventListener('click', function () {
      const textarea = document.getElementById('mensajeReglaInput');
      textarea.value += this.getAttribute('data-var');
      textarea.dispatchEvent(new Event('input'));
    });
  });

  document.getElementById('aplicaAGroup').addEventListener('click', function (e) {
    const pill = e.target.closest('.aplica-pill');
    if (!pill) return;
    this.querySelectorAll('.aplica-pill').forEach((p) => p.classList.remove('selected'));
    pill.classList.add('selected');
    document.querySelector('[name="aplicaA"]').value = pill.getAttribute('data-valor');
  });

  document.getElementById('formRegla').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const reglaId = fd.get('reglaId');

    const payload = {
      nombre: fd.get('nombre'), descripcion: fd.get('descripcion'), timing: fd.get('timing'),
      horas: fd.get('horas'), canal: 'email', aplicaA: fd.get('aplicaA'), mensaje: fd.get('mensaje'),
    };
    const esEdicion = !!reglaId;
    if (esEdicion) payload.reglaId = Number(reglaId);

    try {
      const res = await fetch(`/api/admin/recordatorios/reglas/${esEdicion ? 'actualizar' : 'crear'}.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!data.ok) { alert(data.mensaje || 'No se pudo guardar la regla.'); return; }

      bootstrap.Modal.getInstance(document.getElementById('modalRegla')).hide();
      cargarReglas();
      cargarResumen();
      cargarProgramados();
    } catch (err) {
      alert('Error de conexión al guardar la regla.');
    }
  });
}

function abrirModalRegla(reglaId) {
  const form = document.getElementById('formRegla');
  form.reset();

  if (reglaId) {
    const r = reglasCache.find((x) => x.id === reglaId);
    if (!r) return;

    document.getElementById('modalReglaTitulo').textContent = 'Editar Regla';
    form.elements['reglaId'].value = r.id;
    form.elements['nombre'].value = r.nombre;
    form.elements['descripcion'].value = r.descripcion || '';
    form.elements['timing'].value = r.timing;
    form.elements['horas'].value = r.horas;
    document.getElementById('mensajeReglaInput').value = r.mensaje;
    document.getElementById('contadorMensajeRegla').textContent = r.mensaje.length;

    document.querySelectorAll('.aplica-pill').forEach((p) => p.classList.toggle('selected', p.getAttribute('data-valor') === r.aplica_a));
    document.querySelector('[name="aplicaA"]').value = r.aplica_a;
  } else {
    document.getElementById('modalReglaTitulo').textContent = 'Nueva Regla';
    form.elements['reglaId'].value = '';
    document.getElementById('contadorMensajeRegla').textContent = document.getElementById('mensajeReglaInput').value.length;
    document.querySelectorAll('.aplica-pill').forEach((p) => p.classList.toggle('selected', p.getAttribute('data-valor') === 'todas'));
    document.querySelector('[name="aplicaA"]').value = 'todas';
  }

  new bootstrap.Modal(document.getElementById('modalRegla')).show();
}

window.prepararModalRegla = function () {
  abrirModalRegla(null);
};

// ---------- HISTORIAL ----------

function activarFiltrosHistorial() {
  ['filtroHistBusqueda', 'filtroHistCanal', 'filtroHistEstado'].forEach((id) => document.getElementById(id).addEventListener('input', cargarHistorial));
}

async function cargarHistorial() {
  const buscar = document.getElementById('filtroHistBusqueda').value.trim();
  const canal = document.getElementById('filtroHistCanal').value;
  const estado = document.getElementById('filtroHistEstado').value;

  try {
    const params = new URLSearchParams({ buscar, canal, estado });
    const res = await fetch('/api/admin/recordatorios/historial/listar.php?' + params);
    const data = await res.json();
    if (!data.ok) return;

    document.getElementById('listaHistorial').innerHTML = data.historial.map((h) => `
      <div class="hist-row">
        <div class="flex-grow-1">
          <div class="fw-semibold small">${h.paciente_nombre}</div>
          <div class="text-muted small">${h.servicio_nombre} · ${h.fecha_envio}</div>
        </div>
        <span class="status-badge ${claseEstado(h.estado)}">${textoEstado(h.estado)}</span>
      </div>
    `).join('') || '<div class="empty-state"><i class="bi bi-clock-history"></i><div>Sin historial todavía</div></div>';
  } catch (e) {
    console.error('Error al cargar historial:', e);
  }
}

// ---------- CANALES ----------

function activarCanales() {
  document.getElementById('switchEmail').addEventListener('click', function () {
    this.classList.toggle('on');
  });

  document.getElementById('btnGuardarCanales').addEventListener('click', async function () {
    const payload = {
      emailActivo: document.getElementById('switchEmail').classList.contains('on'),
      emailRemitente: document.getElementById('emailRemitente').value,
      emailNombreRemitente: document.getElementById('emailNombreRemitente').value,
      emailAsunto: document.getElementById('emailAsunto').value,
    };

    try {
      const res = await fetch('/api/admin/recordatorios/canales/guardar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.ok) { alert(data.mensaje || 'No se pudo guardar la configuración.'); return; }
      alert('Configuración guardada.');
    } catch (err) {
      alert('Error de conexión al guardar la configuración.');
    }
  });
}

async function cargarCanales() {
  try {
    const res = await fetch('/api/admin/recordatorios/canales/obtener.php');
    const data = await res.json();
    if (!data.ok) return;

    const c = data.canales;
    document.getElementById('switchEmail').classList.toggle('on', !!Number(c.email_activo));
    document.getElementById('emailRemitente').value = c.email_remitente || '';
    document.getElementById('emailNombreRemitente').value = c.email_nombre_remitente || '';
    document.getElementById('emailAsunto').value = c.email_asunto || '';
  } catch (e) {
    console.error('Error al cargar configuración de canales:', e);
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
