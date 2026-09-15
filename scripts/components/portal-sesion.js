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

// Si el navegador restaura esta página desde su caché de "Atrás/Adelante"
// (bfcache) en vez de volver a cargarla, la sesión pudo haber cambiado
// desde entonces (expiró, se cerró en otra pestaña, etc.) -- forzamos
// una recarga real para que se vuelva a verificar contra el servidor.
window.addEventListener('pageshow', (evento) => {
  if (evento.persisted) {
    window.location.reload();
  }
});

function cargarPerfil() {
  fetch('/api/pacientes/perfil.php')
    .then(res => {
      if (res.status === 401) {
        window.location.href = '../auth/Login.php';
        return Promise.reject(new Error('SESION_EXPIRADA'));
      }
      return res.json();
    })
    .then(datos => {
      // En el header (espacio chico) usamos solo el primer nombre;
      // en el hero (banner grande) usamos el nombre completo.
      const primerNombre = datos.nombre.split(' ')[0];
      document.getElementById('nombrePaciente').textContent = primerNombre;
      document.getElementById('heroNombre').textContent = datos.nombre;
    })
    .catch(err => {
      if (err.message === 'SESION_EXPIRADA') return;
      console.error('Error al cargar el perfil:', err);
    });
}

function activarLogout() {
  const btn = document.getElementById('btnLogout');
  if (!btn) return;

  btn.addEventListener('click', () => {
    window.location.href = '../auth/CerrarSesion.php';
  });
}