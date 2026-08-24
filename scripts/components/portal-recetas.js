/**
 * portal-recetas.js
 * Responsable del tab "Recetas" (solo lectura para el paciente):
 *   - Listar todas sus recetas (activas, completadas, vencidas)
 *   - Ver el detalle de una en un modal (medicamentos, indicaciones)
 *   - Imprimir esa receta
 *
 * [BACKEND] Endpoint que este archivo consume:
 *   GET /api/pacientes/recetas/listar.php
 */

let recetasCache = [];
let recetaActivaId = null;

document.addEventListener('DOMContentLoaded', () => {
  cargarRecetas();
  activarModalReceta();
});

function cargarRecetas() {
  fetch('/api/pacientes/recetas/listar.php')
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(data => {
      if (!data.ok) throw new Error(data.mensaje || 'Error desconocido');
      recetasCache = data.recetas;
      renderRecetas();
    })
    .catch(err => {
      console.error('Error al cargar recetas:', err);
      const contenedor = document.getElementById('recetasList');
      if (contenedor) {
        contenedor.innerHTML = `<div class="text-danger small">No se pudieron cargar tus recetas (${err.message}).</div>`;
      }
    });
}

function estadoRecetaInfo(estado) {
  return {
    activa: { texto: 'Activa', clase: 'badge-confirmada', icono: 'fa-circle-check' },
    completada: { texto: 'Completada', clase: 'badge-pendiente', icono: 'fa-check' },
    vencida: { texto: 'Vencida', clase: 'badge-pendiente', icono: 'fa-clock' },
  }[estado] ?? { texto: estado, clase: 'badge-pendiente', icono: 'fa-file' };
}

function renderRecetas() {
  const resumen = document.getElementById('recetasResumen');
  const contenedor = document.getElementById('recetasList');
  if (!contenedor) return;

  const activas = recetasCache.filter(r => r.estado === 'activa').length;
  if (resumen) {
    resumen.textContent = activas > 0
      ? `Tienes ${activas} receta(s) activa(s).`
      : 'No tienes recetas activas en este momento.';
  }

  if (recetasCache.length === 0) {
    contenedor.innerHTML = '<div class="text-muted small">No tienes recetas registradas todavía.</div>';
    return;
  }

  contenedor.innerHTML = recetasCache.map(r => {
    const info = estadoRecetaInfo(r.estado);
    return `
      <div class="appt-card" role="button" data-receta-id="${r.id}" style="cursor:pointer;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-bold">${r.folio}</div>
            <div class="text-muted small"><i class="fa-solid fa-user-doctor me-1"></i>${r.doctor_nombre}</div>
            <div class="text-muted small"><i class="fa-regular fa-calendar me-1"></i>${r.fecha}</div>
            <div class="text-muted small">${r.diagnostico}</div>
          </div>
          <span class="badge-status ${info.clase}">
            <i class="fa-solid ${info.icono}" style="font-size:.7rem;"></i> ${info.texto}
          </span>
        </div>
      </div>
    `;
  }).join('');

  contenedor.querySelectorAll('[data-receta-id]').forEach(card => {
    card.addEventListener('click', () => abrirModalReceta(Number(card.dataset.recetaId)));
  });
}

function abrirModalReceta(id) {
  const r = recetasCache.find(x => x.id === id);
  if (!r) return;

  recetaActivaId = id;
  document.getElementById('rxFolio').textContent = r.folio;
  document.getElementById('rxMeta').textContent = `${r.doctor_nombre} · ${r.fecha}`;
  document.getElementById('rxDiagnostico').innerHTML = `<strong>Diagnóstico:</strong> ${r.diagnostico}`;

  document.getElementById('rxMedicamentosContainer').innerHTML = r.medicamentos.map(m => `
    <div class="med-block">
      <div class="med-block-title">${m.nombre}${m.concentracion ? ' — ' + m.concentracion : ''}</div>
      <div class="small"><strong>Dosis:</strong> ${m.dosis}</div>
      ${m.frecuencia ? `<div class="small"><strong>Frecuencia:</strong> ${m.frecuencia}</div>` : ''}
      ${m.duracion ? `<div class="small"><strong>Duración:</strong> ${m.duracion}</div>` : ''}
      ${m.instrucciones ? `<div class="small text-muted mt-1">${m.instrucciones}</div>` : ''}
    </div>
  `).join('') || '<div class="text-muted small">Esta receta no tiene medicamentos registrados.</div>';

  const indicWrap = document.getElementById('rxIndicacionesWrap');
  if (r.indicaciones) {
    indicWrap.classList.remove('d-none');
    document.getElementById('rxIndicaciones').textContent = r.indicaciones;
  } else {
    indicWrap.classList.add('d-none');
  }

  const citaWrap = document.getElementById('rxProximaCitaWrap');
  if (r.proxima_cita) {
    citaWrap.classList.remove('d-none');
    document.getElementById('rxProximaCita').textContent = r.proxima_cita;
  } else {
    citaWrap.classList.add('d-none');
  }

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReceta')).show();
}

function activarModalReceta() {
  document.getElementById('rxBtnImprimir').addEventListener('click', () => {
    const r = recetasCache.find(x => x.id === recetaActivaId);
    if (!r) return;

    document.getElementById('prFolio').textContent = r.folio;
    document.getElementById('prFecha').textContent = r.fecha;
    document.getElementById('prPaciente').textContent = document.getElementById('heroNombre')?.textContent || '';
    document.getElementById('prDoctor').textContent = r.doctor_nombre;
    document.getElementById('prDiagnostico').textContent = r.diagnostico;

    document.getElementById('prMedicamentos').innerHTML = r.medicamentos.map(m => `
      <div class="pr-medicamento">
        <strong>${m.nombre}${m.concentracion ? ' — ' + m.concentracion : ''}</strong><br>
        Dosis: ${m.dosis}${m.frecuencia ? ' · ' + m.frecuencia : ''}${m.duracion ? ' · por ' + m.duracion : ''}
        ${m.instrucciones ? `<br><span style="color:#555;">${m.instrucciones}</span>` : ''}
      </div>
    `).join('');

    const indicWrap = document.getElementById('prIndicacionesWrap');
    if (r.indicaciones) {
      indicWrap.style.display = '';
      document.getElementById('prIndicaciones').textContent = r.indicaciones;
    } else {
      indicWrap.style.display = 'none';
    }

    const citaWrap = document.getElementById('prProximaCitaWrap');
    if (r.proxima_cita) {
      citaWrap.style.display = '';
      document.getElementById('prProximaCita').textContent = r.proxima_cita;
    } else {
      citaWrap.style.display = 'none';
    }

    window.print();
  });
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
