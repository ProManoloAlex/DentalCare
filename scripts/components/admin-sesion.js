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

// Si el navegador restaura esta página desde su caché de "Atrás/Adelante"
// (bfcache) en vez de volver a cargarla, la sesión pudo haber cambiado
// desde entonces (expiró, se cerró en otra pestaña, etc.) -- forzamos
// una recarga real para que se vuelva a verificar contra el servidor.
window.addEventListener('pageshow', (evento) => {
  if (evento.persisted) {
    window.location.reload();
  }
});

function cargarPerfilDoctor() {
  fetch('/api/admin/perfil.php')
    .then(res => {
      if (res.status === 401) {
        window.location.href = '../auth/Login.php';
        return Promise.reject(new Error('SESION_EXPIRADA'));
      }
      return res.json();
    })
    .then(datos => {
      const nombreEl = document.getElementById('adminNombre');
      const avatarEl = document.getElementById('adminAvatarInicial');
      if (nombreEl) nombreEl.textContent = datos.nombre;
      if (avatarEl) avatarEl.textContent = datos.nombre.trim().charAt(0).toUpperCase();
    })
    .catch(err => {
      if (err.message === 'SESION_EXPIRADA') return;
      console.error('Error al cargar el perfil del doctor:', err);
    });
}