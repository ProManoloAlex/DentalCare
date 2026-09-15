// Envía el registro sin recargar la página. Si sale bien, en vez de
// redirigir a Login.php con un alert() (como hacía antes el PHP),
// pasa directo al paso de verificación de correo.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-registro');
    if (!form) return;

    const btnSubmit = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        btnSubmit.disabled = true;
        btnSubmit.dataset.textoOriginal = btnSubmit.dataset.textoOriginal || btnSubmit.textContent;
        btnSubmit.textContent = 'Creando cuenta...';

        try {
            const respuesta = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
            });
            const datos = await respuesta.json();

            if (datos.ok) {
                document.getElementById('verificacion-correo').value = datos.correo;
                document.getElementById('verificacion-correo-mostrado').textContent = datos.correo;
                mostrarPanelAuth('panel-verificacion');
                mostrarAvisoAuth(true, datos.mensaje, 'Cuenta creada');
            } else {
                mostrarAvisoAuth(false, datos.mensaje);
            }
        } catch (error) {
            mostrarAvisoAuth(false, 'No se pudo conectar con el servidor. Verifica tu conexión e intenta de nuevo.');
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.textContent = btnSubmit.dataset.textoOriginal;
        }
    });
});