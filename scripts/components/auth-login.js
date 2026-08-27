// Maneja el envío del formulario de login sin recargar la página:
// evita el alert() nativo del navegador y, al fallar, conserva el
// correo ya escrito (solo se limpia la contraseña).
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-login');
    if (!form) return;

    const btnSubmit = form.querySelector('button[type="submit"]');
    const inputContrasenna = document.getElementById('login-contrasenna');
    const modalEl = document.getElementById('modalAvisoLogin');
    const modalTitulo = document.getElementById('modalAvisoLoginTitulo');
    const modalMensaje = document.getElementById('modalAvisoLoginMensaje');
    const modalIcono = document.getElementById('modalAvisoLoginIcono');
    const modal = new bootstrap.Modal(modalEl);

    function mostrarAviso(exito, mensaje) {
        modalTitulo.textContent = exito ? 'Éxito' : 'No se pudo iniciar sesión';
        modalMensaje.textContent = mensaje;
        modalIcono.className = exito
            ? 'fa-solid fa-circle-check text-success fs-1 mb-2'
            : 'fa-solid fa-circle-exclamation text-danger fs-1 mb-2';
        modal.show();
    }

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        btnSubmit.disabled = true;
        btnSubmit.dataset.textoOriginal = btnSubmit.dataset.textoOriginal || btnSubmit.textContent;
        btnSubmit.textContent = 'Iniciando sesión...';

        try {
            const respuesta = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
            });
            const datos = await respuesta.json();

            if (datos.exito) {
                mostrarAviso(true, datos.mensaje);
                setTimeout(() => {
                    window.location.href = datos.destino;
                }, 900);
                return; // no reactivar el botón, ya estamos navegando fuera
            }

            // Falló: se limpia solo la contraseña, el correo se queda como está
            inputContrasenna.value = '';
            inputContrasenna.focus();
            mostrarAviso(false, datos.mensaje);

        } catch (error) {
            mostrarAviso(false, 'No se pudo conectar con el servidor. Verifica tu conexión e intenta de nuevo.');
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.textContent = btnSubmit.dataset.textoOriginal;
        }
    });
});
