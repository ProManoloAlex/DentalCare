<?php

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../config/Conexion_DB.php');

class LoginUsuario {
    private PDO $conexion;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
    }

    public function autenticarUsuario(string $correo, string $contrasenna): array {
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

            $destino = $usuario['rol'] === 'doctor'
                ? '../admin/tablero.html'
                : '../paciente/portal.html';

            return [
                'exito'   => true,
                'mensaje' => '¡Inicio de sesión exitoso!',
                'destino' => $destino,
            ];
        }

        return [
            'exito'   => false,
            'mensaje' => 'Correo o contraseña incorrectos.',
        ];
    }

    private function actualizarUltimoLogin(int $usuarioId): void {
        $stmt = $this->conexion->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$usuarioId]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo      = filter_var(trim($_POST['correo'] ?? ''), FILTER_SANITIZE_EMAIL);
    $contrasenna = $_POST['contrasenna'] ?? '';

    if (empty($correo) || empty($contrasenna)) {
        echo json_encode([
            'exito'   => false,
            'mensaje' => 'Por favor, llene todos los campos.',
        ]);
        exit();
    }

    try {
        $login = new LoginUsuario();
        echo json_encode($login->autenticarUsuario($correo, $contrasenna));
    } catch (PDOException $e) {
        // No se expone el mensaje real del PDOException al cliente
        // (mismo pendiente ya anotado del proyecto: apagar errores
        // visibles de PHP en producción). Aquí ya queda blindado.
        http_response_code(500);
        echo json_encode([
            'exito'   => false,
            'mensaje' => 'Error en el sistema de autenticación. Intenta de nuevo más tarde.',
        ]);
    }
    exit();
}

http_response_code(405);
echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);