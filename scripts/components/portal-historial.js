/**
 * portal-historial.js
 * Responsable únicamente del tab "Historial":
 *   - Filtro por categoría (chips)
 *   - Cargar citas completadas con su diagnóstico/indicaciones
 *
 * [BACKEND] Endpoint que este archivo consume:
 *   GET /api/citas.php?estado=completada
 */

document.addEventListener('DOMContentLoaded', () => {
  cargarHistorial();
  activarFiltroCategorias();
});

function cargarHistorial() {
  fetch('/api/pacientes/citas/listar.php?estado=completada')
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(data => {
      renderResumenHistorial(data.resumen);
      renderHistorial(data.citas);
    })
    .catch(err => {
      console.error('Error al cargar historial:', err);
      const contenedor = document.getElementById('historyList');
      if (contenedor) {
        contenedor.innerHTML = `<div class="text-danger small">No se pudo cargar el historial (${err.message}).</div>`;
      }
    });
}

function renderResumenHistorial(resumen) {
  document.getElementById('histVisitasTotales').textContent = resumen.visitas_totales;
  document.getElementById('histDoctoresAtendidos').textContent = resumen.doctores_atendidos;
  document.getElementById('histTotalPagado').textContent = `$${resumen.total_pagado}`;
}

function renderHistorial(citas) {
  const contenedor = document.getElementById('historyList');
  if (!contenedor) return;

  contenedor.innerHTML = citas.map((cita, index) => {
    const collapseId = `h${index}`;
    const tieneDetalle = cita.diagnostico || cita.indicaciones;

    return `
      <div class="history-item" data-cat="${cita.categoria}">
        <div class="d-flex justify-content-between align-items-start"
             ${tieneDetalle ? `role="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}"` : ''}>
          <div class="d-flex align-items-start gap-2">
            <i class="fa-solid fa-circle-check text-success mt-1"></i>
            <div>
              <div class="fw-bold">${cita.servicio_nombre} <span class="cat-tag cat-${cita.categoria}">${cita.categoria}</span></div>
              <div class="text-muted small">${cita.doctor_nombre} · ${cita.fecha_formateada}</div>
            </div>
          </div>
          <div class="text-end">
            <div class="fw-bold">$${cita.costo}</div>
            <div class="paid-tag">${cita.pagado ? 'Pagado' : 'Pendiente'}</div>
          </div>
        </div>
        ${tieneDetalle ? `
          <div class="collapse" id="${collapseId}">
            <div class="diag-box">
              ${cita.diagnostico ? `<div class="lbl">Diagnóstico</div><div>${cita.diagnostico}</div>` : ''}
              ${cita.indicaciones ? `<div class="lbl mt-2">Indicaciones del doctor</div><div>${cita.indicaciones}</div>` : ''}
              <div class="text-muted small mt-2">${cita.hora} hrs · Costo: $${cita.costo}</div>
            </div>
          </div>` : ''}
      </div>
    `;
  }).join('');

  activarFiltroCategorias();
}

function activarFiltroCategorias() {
  document.querySelectorAll('.filter-chip').forEach(chip => {
    if (chip.dataset.listenerActivo) return;
    chip.dataset.listenerActivo = 'true';

    chip.addEventListener('click', () => {
      document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');

      const filtro = chip.dataset.filter;
      document.querySelectorAll('#historyList .history-item').forEach(item => {
        item.style.display = (filtro === 'Todas' || item.dataset.cat === filtro) ? '' : 'none';
      });
    });
  });
}