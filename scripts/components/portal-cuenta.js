/**
 * portal-cuenta.js
 * Responsable únicamente del tab "Mi Cuenta":
 *   - Saldo total pendiente (tratamientos + citas sueltas sin pagar)
 *   - Detalle de cada concepto pendiente
 *
 * [BACKEND] Endpoint que este archivo consume:
 *   GET /api/pacientes/cuenta/saldo.php
 */

document.addEventListener('DOMContentLoaded', () => {
  cargarCuenta();
  activarFormularioPassword();
});

function activarFormularioPassword() {
  const form = document.getElementById('formCambiarPassword');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const actual = document.getElementById('pwActualPortal').value;
    const nueva = document.getElementById('pwNuevaPortal').value;
    const confirmar = document.getElementById('pwConfirmarPortal').value;

    if (nueva !== confirmar) {
      alert('La nueva contraseña y su confirmación no coinciden.');
      return;
    }

    fetch('/api/pacientes/cuenta/cambiar-password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ actual, nueva }),
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.ok) {
          alert('Tu contraseña se actualizó correctamente.');
          form.reset();
        } else {
          alert(resp.mensaje || 'No se pudo actualizar tu contraseña.');
        }
      })
      .catch(err => {
        console.error('Error al cambiar contraseña:', err);
        alert('Ocurrió un error al actualizar tu contraseña.');
      });
  });
}

function cargarCuenta() {
  fetch('/api/pacientes/cuenta/saldo.php')
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(data => {
      if (!data.ok) throw new Error(data.mensaje || 'Error desconocido');
      renderSaldo(data.cuenta);
    })
    .catch(err => {
      console.error('Error al cargar la cuenta:', err);
      const contenedor = document.getElementById('cuentaDetalleList');
      if (contenedor) {
        contenedor.innerHTML = `<div class="text-danger small">No se pudo cargar tu cuenta (${err.message}).</div>`;
      }
    });
}

function renderSaldo(cuenta) {
  const saldoEl = document.getElementById('saldoTotalPendiente');
  if (saldoEl) saldoEl.textContent = `$${cuenta.saldoTotal.toFixed(2)}`;

  const contenedor = document.getElementById('cuentaDetalleList');
  if (!contenedor) return;

  if (cuenta.detalle.length === 0) {
    contenedor.innerHTML = '<div class="text-muted small">No tienes ningún saldo pendiente. ¡Estás al día!</div>';
    return;
  }

  contenedor.innerHTML = cuenta.detalle.map(item => `
    <div class="treat-card mb-3">
      <div class="d-flex justify-content-between align-items-start">
        <div class="d-flex gap-2 align-items-start">
          <div class="treat-icon"><i class="fa-solid ${item.tipo === 'tratamiento' ? 'fa-tooth' : 'fa-calendar-check'}"></i></div>
          <div>
            <div class="fw-bold">${item.nombre}</div>
            <div class="text-muted small">${item.tipo === 'tratamiento' ? 'Tratamiento' : 'Cita · ' + (item.fecha ?? '')}</div>
          </div>
        </div>
        <div class="text-end">
          <div class="fw-bold" style="color:var(--orange-600);">$${item.saldo.toFixed(2)}</div>
          <div class="text-muted small">de $${item.costoTotal.toFixed(2)}</div>
        </div>
      </div>
    </div>
  `).join('');
}