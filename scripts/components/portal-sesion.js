/**
 * portal-sesion.js
 * Responsable de:
 *   - Mostrar el nombre real del paciente en sesión (header + hero)
 *   - Conectar el botón de cerrar sesión
 *
 * [BACKEND] Endpoint que consume:
 *   GET /api/pacientes/perfil.php
 */

document.addEventListener('DOMContentLoaded', () => {
  cargarPerfil();
  activarLogout();
});

function cargarPerfil() {
  fetch('/api/pacientes/perfil.php')
    .then(res => res.json())
    .then(datos => {
      // En el header (espacio chico) usamos solo el primer nombre;
      // en el hero (banner grande) usamos el nombre completo.
      const primerNombre = datos.nombre.split(' ')[0];
      document.getElementById('nombrePaciente').textContent = primerNombre;
      document.getElementById('heroNombre').textContent = datos.nombre;
    })
    .catch(err => console.error('Error al cargar el perfil:', err));
}

function activarLogout() {
  const btn = document.getElementById('btnLogout');
  if (!btn) return;

  btn.addEventListener('click', () => {
    window.location.href = '../auth/CerrarSesion.php';
  });
}