<?php

require_once __DIR__ . '/../../repositories/TokenRecuperacionRepository.php';
require_once __DIR__ . '/../../repositories/SeguridadRepository.php';
require_once __DIR__ . '/../../repositories/Auditoria.php';
require_once __DIR__ . '/../EmailService.php';

class RecuperacionPasswordService {
    private TokenRecuperacionRepository $repo;
    private SeguridadRepository $seguridadRepo;
    private EmailService $email;

    public function __construct() {
        $this->repo = new TokenRecuperacionRepository();
        $this->seguridadRepo = new SeguridadRepository();
        $this->email = new EmailService();
    }

    /**
     * Siempre responde ok=true (exista o no el correo) para no revelar
     * qué correos están registrados. Solo dispara el envío si sí existe.
     */
    public function solicitar(string $correo): array {
        $correo = trim($correo);
        if ($correo === '') {
            return ['ok' => false, 'mensaje' => 'Ingresa un correo.'];
        }

        $usuario = $this->repo->obtenerUsuarioPacientePorCorreo($correo);

        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $expiraEn = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $this->repo->limpiarTokensPrevios((int) $usuario['id']);
            $this->repo->crearToken((int) $usuario['id'], $token, $expiraEn);
            $this->email->enviarRecuperacionPassword($usuario['correo'], $usuario['nombre'], $token);

            Auditoria::registrar('auth', 'Solicitó recuperación de contraseña', "Usuario #{$usuario['id']}");
        }

        return ['ok' => true, 'mensaje' => 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.'];
    }

    public function validarToken(string $token): array {
        $fila = $this->repo->obtenerPorToken($token);

        if (!$fila || (int) $fila['usado'] === 1) {
            return ['ok' => false, 'mensaje' => 'El enlace no es válido o ya fue utilizado.'];
        }
        if (strtotime($fila['expira_en']) < time()) {
            return ['ok' => false, 'mensaje' => 'El enlace ha expirado. Solicita uno nuevo.'];
        }

        return ['ok' => true];
    }

    public function restablecer(string $token, string $nueva, string $confirmar): array {
        if ($nueva !== $confirmar) {
            return ['ok' => false, 'mensaje' => 'Las contraseñas no coinciden.'];
        }
        if (strlen($nueva) < 6) {
            return ['ok' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres.'];
        }

        $validacion = $this->validarToken($token);
        if (!$validacion['ok']) {
            return $validacion;
        }

        $fila = $this->repo->obtenerPorToken($token);
        $this->seguridadRepo->actualizarPassword((int) $fila['usuario_id'], $nueva);
        $this->repo->marcarUsado($token);

        Auditoria::registrar('auth', 'Restableció su contraseña', "Usuario #{$fila['usuario_id']}");

        return ['ok' => true];
    }
}