/**
 * portal-consentimientos.js
 * Responsable del tab "Consentimientos" y su modal de ver/firmar:
 *   - Listar TODOS los consentimientos del paciente (pendientes,
 *     firmados y rechazados)
 *   - Firmar o rechazar los que estén pendientes
 *
 * [BACKEND] Endpoints que este archivo consume:
 *   GET  /api/pacientes/consentimientos/listar.php
 *   POST /api/pacientes/consentimientos/firmar.php
 *        body: { consentimientoId, estado, firma }
 */

let consentimientosCache = [];
let consentimientoActivoId = null;

document.addEventListener('DOMContentLoaded', () => {
  cargarConsentimientos();
  activarModalConsentimiento();
});

function cargarConsentimientos() {
  fetch('/api/pacientes/consentimientos/listar.php')
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(data => {
      if (!data.ok) throw new Error(data.mensaje || 'Error desconocido');
      consentimientosCache = data.consentimientos;
      renderConsentimientos();
    })
    .catch(err => {
      console.error('Error al cargar consentimientos:', err);
      const contenedor = document.getElementById('consentimientosList');
      if (contenedor) {
        contenedor.innerHTML = `<div class="text-danger small">No se pudieron cargar tus consentimientos (${err.message}).</div>`;
      }
    });
}

function estadoInfo(estado) {
  return {
    pendiente: { texto: 'Pendiente de firma', clase: 'badge-pendiente', icono: 'fa-clock' },
    firmado: { texto: 'Firmado', clase: 'badge-confirmada', icono: 'fa-circle-check' },
    rechazado: { texto: 'Rechazado', clase: 'badge-pendiente', icono: 'fa-circle-xmark' },
  }[estado] ?? { texto: estado, clase: 'badge-pendiente', icono: 'fa-file' };
}

function renderConsentimientos() {
  const resumen = document.getElementById('consentimientosResumen');
  const contenedor = document.getElementById('consentimientosList');
  if (!contenedor) return;

  const pendientes = consentimientosCache.filter(c => c.estado === 'pendiente').length;
  if (resumen) {
    resumen.textContent = pendientes > 0
      ? `Tienes ${pendientes} documento(s) pendiente(s) de firma.`
      : 'No tienes documentos pendientes de firma.';
  }

  if (consentimientosCache.length === 0) {
    contenedor.innerHTML = '<div class="text-muted small">No tienes consentimientos registrados todavía.</div>';
    return;
  }

  contenedor.innerHTML = consentimientosCache.map(c => {
    const info = estadoInfo(c.estado);
    return `
      <div class="appt-card" role="button" data-consentimiento-id="${c.id}" style="cursor:pointer;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-bold">${c.titulo}</div>
            <div class="text-muted small"><i class="fa-solid fa-user-doctor me-1"></i>${c.doctor_nombre}</div>
            <div class="text-muted small"><i class="fa-regular fa-calendar me-1"></i>${c.fecha}</div>
          </div>
          <div class="text-end">
            <span class="badge-status ${info.clase}">
              <i class="fa-solid ${info.icono}" style="font-size:.7rem;"></i> ${info.texto}
            </span>
            ${c.estado === 'pendiente' ? `
              <button class="cta-btn btn-sm mt-2 d-block" data-consentimiento-id="${c.id}" data-firmar-directo="1">
                <i class="fa-solid fa-signature me-1"></i>Firmar
              </button>
            ` : ''}
          </div>
        </div>
      </div>
    `;
  }).join('');

  contenedor.querySelectorAll('[data-consentimiento-id]').forEach(card => {
    card.addEventListener('click', (e) => {
      // Si le dieron clic directo al botón "Firmar", no abras el modal en modo lectura --
      // igual abre el modal, pero ya con el foco listo en el campo de firma.
      abrirModalConsentimiento(Number(card.dataset.consentimientoId));
      if (e.target.closest('[data-firmar-directo]')) {
        setTimeout(() => document.getElementById('mcInputFirma')?.focus(), 300);
      }
    });
  });
}

function abrirModalConsentimiento(id) {
  const c = consentimientosCache.find(x => x.id === id);
  if (!c) return;

  consentimientoActivoId = id;
  document.getElementById('mcTitulo').textContent = c.titulo;
  document.getElementById('mcMeta').textContent = `${c.doctor_nombre} · ${c.fecha}`;
  document.getElementById('mcTexto').textContent = c.texto;

  const zonaFirma = document.getElementById('mcZonaFirma');
  const zonaResultado = document.getElementById('mcZonaResultado');

  if (c.estado === 'pendiente') {
    zonaFirma.classList.remove('d-none');
    zonaResultado.classList.add('d-none');
    document.getElementById('mcInputFirma').value = '';
  } else {
    zonaFirma.classList.add('d-none');
    zonaResultado.classList.remove('d-none');
    document.getElementById('mcResultadoTexto').textContent = c.estado === 'firmado'
      ? `Firmado por ${c.firma} el ${c.fecha_firma}`
      : `Rechazado el ${c.fecha_firma}`;
  }

  // Reinicia el estado de "confirmar rechazo" y el error de firma cada vez
  // que se abre un documento distinto, para que no se quede pegado el
  // estado del consentimiento anterior que se haya visto.
  document.getElementById('mcErrorFirma').classList.add('d-none');
  document.getElementById('mcConfirmarRechazo').classList.add('d-none');
  document.getElementById('mcBotonesPrincipales').classList.remove('d-none');

  // getOrCreateInstance en vez de "new bootstrap.Modal(...)" -- reutiliza
  // la misma instancia del modal en vez de crear una nueva cada vez que
  // se abre, que era lo que dejaba backdrops (fondos oscuros) huérfanos
  // acumulándose y bloqueando la pantalla en gris tras varias aperturas.
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConsentimiento')).show();
}

function activarModalConsentimiento() {
  document.getElementById('mcBtnFirmar').addEventListener('click', () => {
    const firma = document.getElementById('mcInputFirma').value.trim();
    const error = document.getElementById('mcErrorFirma');
    if (!firma) {
      error.classList.remove('d-none');
      document.getElementById('mcInputFirma').focus();
      return;
    }
    error.classList.add('d-none');
    enviarFirma('firmado', firma);
  });

  document.getElementById('mcInputFirma').addEventListener('input', () => {
    document.getElementById('mcErrorFirma').classList.add('d-none');
  });

  document.getElementById('mcBtnRechazar').addEventListener('click', () => {
    document.getElementById('mcBotonesPrincipales').classList.add('d-none');
    document.getElementById('mcConfirmarRechazo').classList.remove('d-none');
  });

  document.getElementById('mcBtnCancelarRechazo').addEventListener('click', () => {
    document.getElementById('mcConfirmarRechazo').classList.add('d-none');
    document.getElementById('mcBotonesPrincipales').classList.remove('d-none');
  });

  document.getElementById('mcBtnConfirmarRechazo').addEventListener('click', () => {
    enviarFirma('rechazado', null);
  });
}

function enviarFirma(estado, firma) {
  fetch('/api/pacientes/consentimientos/firmar.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ consentimientoId: consentimientoActivoId, estado, firma })
  })
    .then(res => res.json())
    .then(resp => {
      if (resp.ok) {
        document.activeElement.blur();
        bootstrap.Modal.getInstance(document.getElementById('modalConsentimiento'))?.hide();
        cargarConsentimientos();
      } else {
        alert(resp.mensaje || 'No se pudo procesar el consentimiento.');
      }
    })
    .catch(err => console.error('Error al firmar el consentimiento:', err));
}

// Red de seguridad: si por cualquier motivo queda un modal-backdrop huérfano
// después de cerrar (bug conocido de Bootstrap 5 con instancias repetidas),
// lo limpiamos apenas se confirme que ya no hay ningún modal abierto.
document.addEventListener('hidden.bs.modal', function () {
  if (document.querySelectorAll('.modal.show').length === 0) {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
  }
});