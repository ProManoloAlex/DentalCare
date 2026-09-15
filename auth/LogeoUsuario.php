<?php

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../config/Conexion_DB.php');
require_once(__DIR__ . '/../repositories/TokenRecordarmeRepository.php');

class LoginUsuario {
    private PDO $conexion;
    private TokenRecordarmeRepository $tokensRecordarmeRepo;

    public function __construct() {
        $this->conexion = Conexion::obtenConexion();
        $this->tokensRecordarmeRepo = new TokenRecordarmeRepository();
    }

    public function autenticarUsuario(string $correo, string $contrasenna, bool $recordarme): array {
        $query = "SELECT id, nombre, correo, contrasenna, rol, correo_verificado
                  FROM usuarios
                  WHERE correo = ? AND activo = 1
                  LIMIT 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();

        if ($usuario && $usuario['contrasenna'] !== null && password_verify($contrasenna, $usuario['contrasenna'])) {

            if ($usuario['rol'] === 'paciente' && (int) $usuario['correo_verificado'] === 0) {
                return [
                    'exito'                => false,
                    'mensaje'              => 'Debes verificar tu correo antes de iniciar sesión.',
                    'requiereVerificacion' => true,
                    'correo'               => $usuario['correo'],
                ];
            }

            session_regenerate_id(true);

            $_SESSION['usuario_id']     = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_correo'] = $usuario['correo'];
            $_SESSION['usuario_rol']    = $usuario['rol'];

            $this->actualizarUltimoLogin($usuario['id']);

            if ($recordarme) {
                $this->crearCookieRecordarme((int) $usuario['id']);
            }

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

    private function crearCookieRecordarme(int $usuarioId): void {
        $this->tokensRecordarmeRepo->eliminarPorUsuario($usuarioId); // 1. limpia cualquier token viejo de este usuario

        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiraEn  = date('Y-m-d H:i:s', strtotime('+30 days'));

        $this->tokensRecordarmeRepo->crearToken($usuarioId, $tokenHash, $expiraEn); // 2. crea el nuevo

        setcookie('recordarme', $usuarioId . ':' . $token, [
            'expires'  => strtotime('+30 days'),
            'path'     => '/',
            'httponly' => true,   // JS del navegador no puede leerla -- protege contra robo por XSS
            'samesite' => 'Lax',
        ]);
    }

    private function actualizarUltimoLogin(int $usuarioId): void {
        $stmt = $this->conexion->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$usuarioId]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo      = filter_var(trim($_POST['correo'] ?? ''), FILTER_SANITIZE_EMAIL);
    $contrasenna = $_POST['contrasenna'] ?? '';
    $recordarme  = isset($_POST['recordarme']);

    if (empty($correo) || empty($contrasenna)) {
        echo json_encode([
            'exito'   => false,
            'mensaje' => 'Por favor, llene todos los campos.',
        ]);
        exit();
    }

    try {
        $login = new LoginUsuario();
        echo json_encode($login->autenticarUsuario($correo, $contrasenna, $recordarme));
    } catch (PDOException $e) {
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