        function mostrarPanel(nombre) {
            // Paneles
            document.getElementById('panel-login').classList.toggle('active', nombre === 'login');
            document.getElementById('panel-registro').classList.toggle('active', nombre === 'registro');

            // Botones del toggle superior
            const btnLogin = document.getElementById('btn-tab-login');
            const btnRegistro = document.getElementById('btn-tab-registro');

            if (nombre === 'login') {
                btnLogin.classList.add('btn-toggle-active', 'text-teal-custom', 'fw-semibold');
                btnLogin.classList.remove('text-secondary');
                btnRegistro.classList.remove('btn-toggle-active', 'text-teal-custom', 'fw-semibold');
                btnRegistro.classList.add('text-secondary');
            } else {
                btnRegistro.classList.add('btn-toggle-active', 'text-teal-custom', 'fw-semibold');
                btnRegistro.classList.remove('text-secondary');
                btnLogin.classList.remove('btn-toggle-active', 'text-teal-custom', 'fw-semibold');
                btnLogin.classList.add('text-secondary');
            }
        }