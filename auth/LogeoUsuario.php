<?php

session_start();
require_once(__DIR__ . '/../config/Conexion_DB.php');

class LoginUsuario {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function autenticarUsuario(string $correo, string $contrasenna): void {
        try {
            $query = "SELECT id, nombre, correo, contrasenna, rol 
                      FROM usuarios 
                      WHERE correo = ? AND activo = 1 
                      LIMIT 1";
            $stmt = $this->conexion->prepare($query);
            $stmt->execute([$correo]);
            $usuario = $stmt->fetch();

            // Si el doctor registró a este paciente sin darle acceso al
            // portal, su contrasenna queda NULL — no debe poder entrar.
            if ($usuario && $usuario['contrasenna'] !== null && password_verify($contrasenna, $usuario['contrasenna'])) {

                session_regenerate_id(true);

                $_SESSION['usuario_id']     = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_correo'] = $usuario['correo'];
                $_SESSION['usuario_rol']    = $usuario['rol'];

                $this->actualizarUltimoLogin($usuario['id']);

                echo '<script>alert("¡Inicio de sesión exitoso!");</script>';

                if ($usuario['rol'] === 'doctor') {
                    echo '<script>window.location.href = "../admin/tablero.html";</script>';
                } else {
                    echo '<script>window.location.href = "../paciente/portal.html";</script>';
                }
                exit();

            } else {
                echo '
                <script>
                    alert("Correo o contraseña incorrectos");
                    window.location.href = "Login.php";
                </script>';
                exit();
            }

        } catch (PDOException $e) {
            die("Error en el sistema de autenticación: " . $e->getMessage());
        }
    }

    private function actualizarUltimoLogin(int $usuarioId): void {
        $stmt = $this->conexion->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$usuarioId]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo      = filter_var(trim($_POST['correo'] ?? ''), FILTER_SANITIZE_EMAIL);
    $contrasenna = $_POST['contrasenna'] ?? '';

    if (!empty($correo) && !empty($contrasenna)) {
        $login = new LoginUsuario();
        $login->autenticarUsuario($correo, $contrasenna);
    } else {
        echo '<script>alert("Por favor, llene todos los campos."); window.location.href = "Login.php";</script>';
    }
}