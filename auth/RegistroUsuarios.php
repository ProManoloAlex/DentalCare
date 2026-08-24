<?php

require_once(__DIR__ . '/../config/Conexion_DB.php');

class RegistroUsuarios {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function registrarUsuario(string $nombre, string $correo, string $contrasenna): void {
        if ($this->correoExiste($correo)) {
            echo '
            <script>
                alert("Este correo ya está registrado en el sistema.");
                window.location.href = "Login.php";
            </script>';
            return;
        }

        $contrasenna_hash = password_hash($contrasenna, PASSWORD_BCRYPT);

        try {
            // Usamos una transacción: si falla el insert en "pacientes",
            // no se debe quedar a medias el insert en "usuarios".
            $this->conexion->beginTransaction();

            $query = "INSERT INTO usuarios (nombre, correo, contrasenna, rol) VALUES (?, ?, ?, 'paciente')";
            $stmt = $this->conexion->prepare($query);
            $stmt->execute([$nombre, $correo, $contrasenna_hash]);

            $usuarioId = (int) $this->conexion->lastInsertId();

            // Toda cuenta 'paciente' necesita su fila en pacientes,
            // porque citas.paciente_id apunta a pacientes.id (no a usuarios.id)
            $stmtPaciente = $this->conexion->prepare("INSERT INTO pacientes (usuario_id) VALUES (?)");
            $stmtPaciente->execute([$usuarioId]);

            $this->conexion->commit();

            echo '
            <script>
                alert("¡Cuenta creada exitosamente! Ya puede iniciar sesión.");
                window.location.href = "Login.php";
            </script>';

        } catch (PDOException $e) {
            $this->conexion->rollBack();
            die("Error crítico al almacenar el usuario: " . $e->getMessage());
        }
    }

    private function correoExiste(string $correo): bool {
        $query = "SELECT COUNT(*) FROM usuarios WHERE correo = ?";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$correo]);
        return $stmt->fetchColumn() > 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $correo      = filter_var(trim($_POST['correo'] ?? ''), FILTER_SANITIZE_EMAIL);
    $contrasenna = $_POST['contrasenna'] ?? '';

    if (!empty($nombre) && !empty($correo) && !empty($contrasenna)) {
        $registro = new RegistroUsuarios();
        $registro->registrarUsuario($nombre, $correo, $contrasenna);
    } else {
        echo '
        <script>
            alert("Por favor, rellene todos los campos obligatorios.");
            window.location.href = "Login.php";
        </script>';
    }
}