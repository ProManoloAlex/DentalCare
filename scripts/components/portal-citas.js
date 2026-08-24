/**
 * portal-citas.js
 * Responsable únicamente del tab "Mis Citas":
 *   - Cargar las citas próximas del paciente en sesión
 *   - Manejar el click en "Cancelar cita"
 *
 * [BACKEND] Endpoints que este archivo consume:
 *   GET  /api/pacientes/citas/listar.php?estado=proximas
 *   POST /api/pacientes/citas/cancelar.php   body: { cita_id }
 */

document.addEventListener('DOMContentLoaded', () => {
  cargarCitasProximas();
  activarBotonesCancelar();
});

function cargarCitasProximas() {
  fetch('/api/pacientes/citas/listar.php?estado=proximas')
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(citas => renderCitas(citas))
    .catch(err => {
      console.error('Error al cargar citas:', err);
      const contenedor = document.getElementById('citasProximasList');
      if (contenedor) {
        contenedor.innerHTML = `<div class="text-danger small">No se pudieron cargar las citas (${err.message}). Revisa la consola.</div>`;
      }
    });
}

function renderCitas(citas) {
  const contenedor = document.getElementById('citasProximasList');
  if (!contenedor) return;

  if (citas.length === 0) {
    contenedor.innerHTML = '<div class="text-muted small">No tienes citas próximas.</div>';
    return;
  }

  contenedor.innerHTML = citas.map(cita => `
    <div class="appt-card" data-cita-id="${cita.id}">
      <div class="d-flex justify-content-between align-items-start">
        <div class="d-flex gap-3">
          <div class="date-chip">
            <div class="mon">${cita.mes_abrev}</div>
            <div class="day">${cita.dia}</div>
          </div>
          <div>
            <div class="fw-bold">${cita.servicio_nombre}</div>
            <div class="text-muted small"><i class="fa-solid fa-user-doctor me-1"></i>${cita.doctor_nombre}</div>
            <div class="text-muted small"><i class="fa-regular fa-clock me-1"></i>${cita.hora} · ${cita.consultorio}</div>
          </div>
        </div>
        <span class="badge-status ${cita.estado === 'confirmada' ? 'badge-confirmada' : 'badge-pendiente'}">
          <i class="fa-solid fa-circle" style="font-size:.5rem;"></i>
          ${cita.estado === 'confirmada' ? 'Confirmada' : 'Pendiente confirmación'}
        </span>
      </div>
      ${cita.notas ? `<div class="note-pill mt-3"><i class="fa-regular fa-note-sticky me-1"></i>${cita.notas}</div>` : ''}
      <a href="#" class="cancel-link d-inline-block mt-2" data-cancelar-cita="${cita.id}">
        <i class="fa-regular fa-circle-xmark me-1"></i>Cancelar cita
      </a>
    </div>
  `).join('');

  activarBotonesCancelar();
}

function activarBotonesCancelar() {
  document.querySelectorAll('[data-cancelar-cita]').forEach(link => {
    // Evitar registrar el mismo listener dos veces al re-renderizar
    if (link.dataset.listenerActivo) return;
    link.dataset.listenerActivo = 'true';

    link.addEventListener('click', (e) => {
      e.preventDefault();
      const citaId = link.dataset.cancelarCita;

      const confirmar = confirm('¿Seguro que quieres cancelar esta cita?');
      if (!confirmar) return;

      cancelarCita(citaId);
    });
  });
}

function cancelarCita(citaId) {
  fetch('/api/pacientes/citas/cancelar.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ cita_id: citaId })
  })
    .then(res => res.json())
    .then(resp => {
      if (resp.ok) {
        document.querySelector(`[data-cita-id="${citaId}"]`)?.remove();
        if (typeof cargarDashboard === 'function') {
          cargarDashboard();
        }
      } else {
        alert(resp.mensaje || 'No se pudo cancelar la cita.');
      }
    })
    .catch(err => console.error('Error al cancelar cita:', err));
}