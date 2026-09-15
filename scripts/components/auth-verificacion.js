// Maneja la captura del código de verificación y el link de reenvío.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-verificacion');
    if (!form) return;

    const btnSubmit = form.querySelector('button[type="submit"]');
    const inputCodigo = document.getElementById('verificacion-codigo');
    const inputCorreoOculto = document.getElementById('verificacion-correo');
    const linkReenviar = document.getElementById('link-reenviar-codigo');

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        btnSubmit.disabled = true;
        btnSubmit.dataset.textoOriginal = btnSubmit.dataset.textoOriginal || btnSubmit.textContent;
        btnSubmit.textContent = 'Verificando...';

        try {
            const respuesta = await fetch('verificar-codigo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    correo: inputCorreoOculto.value,
                    codigo: inputCodigo.value.trim(),
                }),
            });
            const datos = await respuesta.json();

            if (datos.ok) {
                mostrarAvisoAuth(true, datos.mensaje);
                document.getElementById('login-correo').value = inputCorreoOculto.value;
                mostrarPanelAuth('panel-login');
                inputCodigo.value = '';
            } else {
                mostrarAvisoAuth(false, datos.mensaje);
                inputCodigo.value = '';
                inputCodigo.focus();
            }
        } catch (error) {
            mostrarAvisoAuth(false, 'No se pudo conectar con el servidor. Verifica tu conexión e intenta de nuevo.');
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.textContent = btnSubmit.dataset.textoOriginal;
        }
    });

    linkReenviar.addEventListener('click', async (evento) => {
        evento.preventDefault();
        const textoOriginal = linkReenviar.textContent;
        linkReenviar.textContent = 'Enviando...';

        try {
            const respuesta = await fetch('reenviar-codigo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ correo: inputCorreoOculto.value }),
            });
            const datos = await respuesta.json();
            mostrarAvisoAuth(datos.ok, datos.mensaje);
        } catch (error) {
            mostrarAvisoAuth(false, 'No se pudo conectar con el servidor.');
        } finally {
            linkReenviar.textContent = textoOriginal;
        }
    });
});