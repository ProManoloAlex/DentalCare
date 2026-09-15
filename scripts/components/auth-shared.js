// Funciones compartidas entre auth-login.js, auth-registro.js y
// auth-verificacion.js -- evita repetir el modal de aviso y el
// cambio de panel activo en cada script por separado.

window.mostrarAvisoAuth = function (exito, mensaje, tituloExito = 'Éxito', tituloError = 'No se pudo completar') {
    const modalEl = document.getElementById('modalAvisoLogin');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.getElementById('modalAvisoLoginTitulo').textContent = exito ? tituloExito : tituloError;
    document.getElementById('modalAvisoLoginMensaje').textContent = mensaje;
    document.getElementById('modalAvisoLoginIcono').className = exito
        ? 'fa-solid fa-circle-check text-success fs-1 mb-2'
        : 'fa-solid fa-circle-exclamation text-danger fs-1 mb-2';
    modal.show();
};

window.mostrarPanelAuth = function (idPanel) {
    document.querySelectorAll('.auth-panel').forEach(p => p.classList.remove('active'));
    document.getElementById(idPanel).classList.add('active');

    // El paso de verificación no es una "pestaña" -- se llega ahí
    // después de registrarse o de intentar entrar sin verificar,
    // así que se oculta el switch Login/Registrarse mientras dure
    const tabs = document.getElementById('auth-tabs-wrapper');
    if (tabs) tabs.style.setProperty('display', idPanel === 'panel-verificacion' ? 'none' : 'flex', 'important');
};