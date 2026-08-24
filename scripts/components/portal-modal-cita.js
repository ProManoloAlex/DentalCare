/**
 * portal-modal-cita.js
 * Responsable únicamente del modal "Solicitar Cita":
 *   - Paso 1: elegir servicio
 *   - Paso 2: elegir doctor, fecha y notas
 *   - Enviar la solicitud
 *
 * [BACKEND] Endpoints que este archivo consume:
 *   GET  /api/pacientes/catalogos/servicios.php?activo=1   (para pintar #serviceList)
 *   GET  /api/pacientes/catalogos/doctores.php             (para pintar #doctorList)
 *   POST /api/pacientes/citas/solicitar.php       body: { servicio_id, doctor_id, fecha, notas }
 */

let selectedService = { id: 1, name: 'Limpieza Dental', price: '$50', duration: '45 min' };
let selectedDoctor = null;
let selectedHora = null;

document.addEventListener('DOMContentLoaded', () => {
  restringirFechaMinima();
  cargarServicios();
  cargarDoctores();
  activarNavegacionPasos();
  activarCambioFecha();
  activarContadorNotas();
  activarEnvioSolicitud();
  activarResetAlCerrar();
});

function cargarServicios() {
  fetch('/api/pacientes/catalogos/servicios.php')
    .then(res => res.json())
    .then(servicios => {
      const contenedor = document.getElementById('serviceList');
      if (!contenedor || servicios.length === 0) return;

      contenedor.innerHTML = servicios.map((s, index) => `
        <div class="service-option ${index === 0 ? 'selected' : ''}"
             data-servicio-id="${s.id}"
             data-name="${s.nombre}"
             data-price="$${s.precio}"
             data-duration="${s.duracion_min} min">
          <div class="fw-semibold">${s.nombre}</div>
          <div class="text-muted small">${s.categoria} · ${s.duracion_min} min</div>
          <div class="price position-absolute" style="top:.85rem; right:1rem;">$${s.precio}</div>
          ${index === 0 ? '<div class="check-badge"><i class="fa-solid fa-check"></i></div>' : ''}
        </div>
      `).join('');

      selectedService = {
        id: servicios[0].id,
        name: servicios[0].nombre,
        price: `$${servicios[0].precio}`,
        duration: `${servicios[0].duracion_min} min`,
      };

      activarSeleccionServicio();
    })
    .catch(err => console.error('Error al cargar servicios:', err));
}

function cargarDoctores() {
  fetch('/api/pacientes/catalogos/doctores.php')
    .then(res => res.json())
    .then(doctores => {
      const contenedor = document.getElementById('doctorList');
      if (!contenedor) return;

      contenedor.innerHTML = doctores.map(d => `
        <div class="doctor-option d-flex align-items-center gap-2" data-doctor-id="${d.id}" data-name="${d.nombre}">
          <div class="doctor-avatar"><i class="fa-solid fa-user"></i></div>
          <div>
            <div class="fw-semibold small">${d.nombre}</div>
            <div class="text-muted small">${d.especialidad ?? ''}</div>
          </div>
        </div>
      `).join('');

      activarSeleccionDoctor();
    })
    .catch(err => console.error('Error al cargar doctores:', err));
}

function restringirFechaMinima() {
  const inputFecha = document.getElementById('fechaPreferida');
  if (!inputFecha) return;
  const hoy = new Date().toISOString().split('T')[0];
  inputFecha.setAttribute('min', hoy);
}

function activarSeleccionServicio() {
  document.querySelectorAll('.service-option').forEach(opt => {
    opt.addEventListener('click', () => {
      document.querySelectorAll('.service-option').forEach(o => {
        o.classList.remove('selected');
        const badge = o.querySelector('.check-badge');
        if (badge) badge.remove();
      });

      opt.classList.add('selected');
      const badge = document.createElement('div');
      badge.className = 'check-badge';
      badge.innerHTML = '<i class="fa-solid fa-check"></i>';
      opt.appendChild(badge);

      selectedService = {
        id: opt.dataset.servicioId,
        name: opt.dataset.name,
        price: opt.dataset.price,
        duration: opt.dataset.duration,
      };
    });
  });
}

function activarSeleccionDoctor() {
  document.querySelectorAll('.doctor-option').forEach(opt => {
    opt.addEventListener('click', () => {
      document.querySelectorAll('.doctor-option').forEach(o => o.classList.remove('selected'));
      opt.classList.add('selected');
      selectedDoctor = { id: opt.dataset.doctorId, name: opt.dataset.name };
      actualizarEstadoBotonEnviar();
      cargarHorariosDisponibles();
    });
  });
}

function activarCambioFecha() {
  const inputFecha = document.getElementById('fechaPreferida');
  if (!inputFecha) return;
  inputFecha.addEventListener('change', cargarHorariosDisponibles);
}

function cargarHorariosDisponibles() {
  const contenedor = document.getElementById('horariosDisponibles');
  const fecha = document.getElementById('fechaPreferida').value;

  selectedHora = null;
  actualizarEstadoBotonEnviar();

  if (!selectedDoctor || !fecha) {
    contenedor.innerHTML = '<div class="text-muted small">Selecciona doctor y fecha primero.</div>';
    return;
  }

  contenedor.innerHTML = '<div class="text-muted small">Buscando horarios disponibles...</div>';

  const url = `/api/pacientes/citas/horarios-disponibles.php?doctor_id=${selectedDoctor.id}&servicio_id=${selectedService.id}&fecha=${fecha}`;

  fetch(url)
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(horarios => {
      if (horarios.length === 0) {
        contenedor.innerHTML = '<div class="text-muted small">No hay horarios disponibles ese día. Prueba otra fecha.</div>';
        return;
      }

      contenedor.innerHTML = `
        <div class="d-flex flex-wrap gap-2">
          ${horarios.map(h => `
            <button type="button" class="btn btn-sm btn-outline-secondary" data-hora="${h}">${h}</button>
          `).join('')}
        </div>
      `;

      contenedor.querySelectorAll('[data-hora]').forEach(btn => {
        btn.addEventListener('click', () => {
          contenedor.querySelectorAll('[data-hora]').forEach(b => {
            b.classList.add('btn-outline-secondary');
            b.style.backgroundColor = '';
            b.style.color = '';
            b.style.borderColor = '';
          });
          btn.classList.remove('btn-outline-secondary');
          btn.style.backgroundColor = 'var(--teal-500, #0d9488)';
          btn.style.color = '#fff';
          btn.style.borderColor = 'var(--teal-500, #0d9488)';
          selectedHora = btn.dataset.hora;
          actualizarEstadoBotonEnviar();
        });
      });
    })
    .catch(err => {
      console.error('Error al cargar horarios:', err);
      contenedor.innerHTML = '<div class="text-danger small">No se pudieron cargar los horarios.</div>';
    });
}

function activarNavegacionPasos() {
  document.getElementById('btnContinuar').addEventListener('click', irAPaso2);
  document.getElementById('btnAtras').addEventListener('click', irAPaso1);
  document.getElementById('btnCambiar').addEventListener('click', (e) => {
    e.preventDefault();
    irAPaso1();
  });
}

function irAPaso2() {
  document.getElementById('step1').classList.add('d-none');
  document.getElementById('step2').classList.remove('d-none');
  document.getElementById('stepLabel').textContent = 'Paso 2 de 2';
  document.getElementById('stepProgress').classList.add('full');
  document.getElementById('selService').textContent = selectedService.name;
  document.getElementById('selDetail').textContent = `${selectedService.duration} · ${selectedService.price}`;
}

function irAPaso1() {
  document.getElementById('step2').classList.add('d-none');
  document.getElementById('step1').classList.remove('d-none');
  document.getElementById('stepLabel').textContent = 'Paso 1 de 2';
  document.getElementById('stepProgress').classList.remove('full');
}

function activarContadorNotas() {
  const textarea = document.getElementById('notasTxt');
  textarea.addEventListener('input', (e) => {
    document.getElementById('notasCount').textContent = e.target.value.length;
  });
}

function actualizarEstadoBotonEnviar() {
  const btn = document.getElementById('btnEnviar');
  if (selectedDoctor && selectedHora) {
    btn.disabled = false;
    btn.style.background = 'var(--teal-500)';
  } else {
    btn.disabled = true;
    btn.style.background = '#c7d0ce';
  }
}

function activarEnvioSolicitud() {
  document.getElementById('btnEnviar').addEventListener('click', () => {
    if (!selectedDoctor || !selectedHora) return;

    const fecha = document.getElementById('fechaPreferida').value;
    const notas = document.getElementById('notasTxt').value;

    if (!fecha) {
      alert('Selecciona una fecha preferida.');
      return;
    }

    enviarSolicitudCita({
      servicio_id: selectedService.id,
      doctor_id: selectedDoctor.id,
      fecha,
      hora: selectedHora,
      notas,
    });
  });
}

function enviarSolicitudCita(payload) {
  fetch('/api/pacientes/citas/solicitar.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
    .then(res => res.json())
    .then(resp => {
      if (resp.ok) {
        cerrarYResetearModal();
        if (typeof cargarCitasProximas === 'function') {
          cargarCitasProximas(); // definida en portal-citas.js, refresca la lista
        }
        if (typeof cargarDashboard === 'function') {
          cargarDashboard(); // definida en portal-dashboard.js, refresca las stat cards
        }
      } else {
        alert(resp.mensaje || 'No se pudo enviar la solicitud.');
      }
    })
    .catch(err => console.error('Error al enviar solicitud:', err));
}

function cerrarYResetearModal() {
  // Quitamos el foco del botón activo antes de ocultar el modal;
  // si no, Bootstrap marca aria-hidden en un elemento que aún tiene foco
  // (advertencia de accesibilidad, no rompe nada, pero mejor evitarla)
  document.activeElement.blur();

  bootstrap.Modal.getInstance(document.getElementById('modalCita')).hide();
  setTimeout(() => {
    irAPaso1();
    document.getElementById('notasTxt').value = '';
    document.getElementById('notasCount').textContent = '0';
    selectedHora = null;
    selectedDoctor = null;
    document.getElementById('horariosDisponibles').innerHTML =
      '<div class="text-muted small">Selecciona doctor y fecha primero.</div>';
  }, 300);
}

function activarResetAlCerrar() {
  document.getElementById('modalCita').addEventListener('hidden.bs.modal', () => {
    irAPaso1();
  });
}