/**
 * portal-tratamientos.js
 * Responsable únicamente del tab "Tratamientos":
 *   - Cargar tratamientos activos del paciente con su progreso
 *
 * [BACKEND] Endpoint que este archivo consume:
 *   GET /api/tratamientos.php?estado=activo
 */

document.addEventListener('DOMContentLoaded', () => {
  cargarTratamientos();
});

function cargarTratamientos() {
  fetch('/api/pacientes/tratamientos/listar.php')
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(tratamientos => renderTratamientos(tratamientos))
    .catch(err => {
      console.error('Error al cargar tratamientos:', err);
      const contenedor = document.getElementById('tratamientosList');
      if (contenedor) {
        contenedor.innerHTML = `<div class="text-danger small">No se pudieron cargar los tratamientos (${err.message}).</div>`;
      }
    });
}

function renderTratamientos(tratamientos) {
  const resumen = document.getElementById('tratamientosResumen');
  const contenedor = document.getElementById('tratamientosList');
  if (!contenedor) return;

  if (resumen) {
    resumen.textContent = tratamientos.length > 0
      ? `Tienes ${tratamientos.length} tratamiento(s) activo(s) en progreso.`
      : '';
  }

  if (tratamientos.length === 0) {
    contenedor.innerHTML = '<div class="text-muted small">No tienes tratamientos activos.</div>';
    return;
  }

  contenedor.innerHTML = tratamientos.map(t => {
    const barraColor = t.categoria === 'Ortodoncia' ? 'progress-bar-teal' : 'progress-bar-amber';

    return `
      <div class="treat-card" data-tratamiento-id="${t.id}">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="d-flex gap-2 align-items-start">
            <div class="treat-icon"><i class="fa-solid fa-tooth"></i></div>
            <div>
              <div class="fw-bold">${t.nombre}</div>
              <div class="text-muted small">${t.doctor_nombre}</div>
            </div>
          </div>
          <span class="cat-tag cat-${t.categoria}">${t.categoria}</span>
        </div>
        <div class="text-muted small mb-2">${t.descripcion ?? ''}</div>
        <div class="d-flex justify-content-between small mb-1">
          <span>Progreso del tratamiento</span><span class="fw-bold">${t.progreso_porcentaje}%</span>
        </div>
        <div class="progress mb-1"><div class="progress-bar ${barraColor}" style="width:${t.progreso_porcentaje}%"></div></div>
        <div class="text-muted small mb-3">${t.sesiones_completadas} sesiones completadas / ${t.sesiones_totales} total</div>
        <div class="row small mb-3">
          <div class="col-6"><span class="text-muted">Inicio del tratamiento</span><br><span class="fw-semibold">${t.fecha_inicio}</span></div>
          <div class="col-6"><span class="text-muted">Finalización estimada</span><br><span class="fw-semibold">${t.fecha_fin_estimada ?? '—'}</span></div>
        </div>
        <div class="d-flex justify-content-between small mb-1">
          <span>Estado financiero</span>
          <span class="${t.pagado_completo ? 'paid-tag' : ''}" style="${t.pagado_completo ? '' : 'color:var(--orange-600); font-weight:700;'}">
            ${t.pagado_completo ? 'Pagado completamente' : `Pendiente: $${t.saldo_pendiente}`}
          </span>
        </div>
        <div class="d-flex justify-content-between small fw-bold mb-1">
          <span>Costo total: $${t.costo_total}</span><span>Pagado: $${t.monto_pagado}</span>
        </div>
        <div class="progress"><div class="progress-bar ${barraColor}" style="width:${t.avance_pago_porcentaje}%"></div></div>
      </div>
    `;
  }).join('');
}