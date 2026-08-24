<?php $resetExitoso = isset($_GET['reset']) && $_GET['reset'] === 'exitoso'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Odontológico - Gestión Integral de Clínica Dental</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Remixicon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">

    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="icon" type="image/svg+xml" href="https://api.iconify.design/fluent-emoji-flat:tooth.svg">

    <link rel="stylesheet" href="../styles/login.css">
</head>

<body>
    <div class="container d-flex justify-content-center align-items-center min-vh-100 py-4">

        <div class="card shadow-lg border-0 rounded-4" style="max-width:440px; width:100%;">

            <div class="card-body p-4 p-md-5">

                <!-- Logo -->
                <div class="text-center mb-4">
                    <div class="bg-teal-custom rounded-circle d-inline-flex justify-content-center align-items-center mb-3"
                        style="width:70px;height:70px;">
                        <i class="fa-solid fa-tooth text-white fs-2"></i>
                    </div>
                    <h2 class="fw-bold mb-1">
                        Sistema Odontológico
                    </h2>
                    <p class="text-muted mb-0">
                        Gestión integral de clínica dental
                    </p>
                </div>

                <!-- Botones de navegación entre paneles -->
                <div class="bg-light rounded-3 p-1 d-flex mb-4">
                    <button id="btn-tab-login" type="button" class="btn flex-fill btn-toggle-active text-teal-custom fw-semibold" onclick="mostrarPanel('login')">
                        Iniciar Sesión
                    </button>
                    <button id="btn-tab-registro" type="button" class="btn flex-fill text-secondary" onclick="mostrarPanel('registro')">
                        Registrarse
                    </button>
                </div>

                <!-- Panel: Iniciar Sesión -->
                <div id="panel-login" class="auth-panel active">
                
                <?php if ($resetExitoso): ?>
                <div class="alert alert-success">Tu contraseña se actualizó. Ya puedes iniciar sesión.</div>
                <?php endif; ?>
                    <form action="LogeoUsuario.php" method="POST">
                        <div class="mb-3">
                            <label for="login-correo" class="form-label fw-semibold">Correo Electrónico</label>
                            <input type="email" id="login-correo" name="correo" class="form-control py-2 rounded-3 focus-teal" placeholder="correo@ejemplo.com" required>
                        </div>
                        <div class="mb-3">
                            <label for="login-contrasenna" class="form-label fw-semibold">Contraseña</label>
                            <input type="password" id="login-contrasenna" name="contrasenna" class="form-control py-2 rounded-3 focus-teal" placeholder="••••••••" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="recordarme">
                                <label class="form-check-label" for="recordarme">Recordarme</label>
                            </div>
                            <a href="recuperar-password.html" class="text-teal-custom">¿Olvidó su contraseña?</a>
                        </div>
                        <button type="submit" class="btn bg-teal-custom text-white w-100 py-2 shadow-sm">
                            Iniciar Sesión
                        </button>
                    </form>
                </div>

            <!-- Panel: Registrarse -->
            <div id="panel-registro" class="auth-panel">
                <form action="RegistroUsuarios.php" method="POST">
                    <div class="mb-3">
                        <label for="reg-nombre" class="form-label fw-semibold">Nombre Completo</label>
                        <input type="text" id="reg-nombre" name="nombre" class="form-control py-2 rounded-3 focus-teal" placeholder="Ingrese su nombre">
                    </div>
                    <div class="mb-3">
                        <label for="reg-correo" class="form-label fw-semibold">Correo Electrónico</label>
                        <input type="email" id="reg-correo" name="correo" class="form-control py-2 rounded-3 focus-teal" placeholder="correo@ejemplo.com">
                    </div>
                    <div class="mb-3">
                        <label for="reg-contrasenna" class="form-label fw-semibold">Contraseña</label>
                        <input type="password" id="reg-contrasenna" name="contrasenna" class="form-control py-2 rounded-3 focus-teal" placeholder="••••••••">
                    </div>  
                    <button type="submit" class="btn bg-teal-custom text-white w-100 py-2 shadow-sm">
                        Crear Cuenta
                    </button>
                </form>  
            </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script src="../scripts/components/auth-tabs.js"></script>
</body>

</html>