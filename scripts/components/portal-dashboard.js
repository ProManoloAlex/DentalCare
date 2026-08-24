/**
 * portal-dashboard.js
 * Responsable de las 3 stat cards del inicio del portal
 * (Citas próximas, Tratamientos activos, Saldo pendiente).
 *
 * [BACKEND] Endpoint que consume:
 *   GET /api/pacientes/dashboard.php
 */

document.addEventListener('DOMContentLoaded', () => {
  cargarDashboard();
});

function cargarDashboard() {
  fetch('/api/pacientes/dashboard.php')
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(datos => {
      document.getElementById('statCitasProximas').textContent = datos.citas_proximas;
      document.getElementById('statTratamientosActivos').textContent = datos.tratamientos_activos;
      document.getElementById('statSaldoPendiente').textContent = `$${datos.saldo_pendiente}`;
    })
    .catch(err => console.error('Error al cargar el dashboard:', err));
}