// Botón de "ojo" reutilizable para mostrar/ocultar contraseña.
// Cualquier <button class="toggle-password" data-target="id-del-input">
// funciona automáticamente con este script, sin código extra por campo.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-password').forEach((boton) => {
        boton.addEventListener('click', () => {
            const input = document.getElementById(boton.dataset.target);
            if (!input) return;

            const icono = boton.querySelector('i');
            const mostrandoTexto = input.type === 'text';

            input.type = mostrandoTexto ? 'password' : 'text';
            icono.classList.toggle('fa-eye', mostrandoTexto);
            icono.classList.toggle('fa-eye-slash', !mostrandoTexto);
        });
    });
});