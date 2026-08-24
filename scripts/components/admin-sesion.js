/**
 * admin-sesion.js
 * Muestra el nombre real del doctor en sesión en el header del tablero.
 * (El logout no necesita JS: ya es un link directo a CerrarSesion.php
 * dentro de admin-sidebar.js)
 *
 * [BACKEND] Endpoint que consume:
 *   GET /api/admin/perfil.php
 */

document.addEventListener('DOMContentLoaded', () => {
  cargarPerfilDoctor();
});

function cargarPerfilDoctor() {
  fetch('/api/admin/perfil.php')
    .then(res => res.json())
    .then(datos => {
      const nombreEl = document.getElementById('adminNombre');
      const avatarEl = document.getElementById('adminAvatarInicial');
      if (nombreEl) nombreEl.textContent = datos.nombre;
      if (avatarEl) avatarEl.textContent = datos.nombre.trim().charAt(0).toUpperCase();
    })
    .catch(err => console.error('Error al cargar el perfil del doctor:', err));
}