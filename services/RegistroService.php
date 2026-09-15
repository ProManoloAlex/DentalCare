<?php

require_once __DIR__ . '/../repositories/RegistroRepository.php';
require_once __DIR__ . '/../repositories/CodigoVerificacionRepository.php';
require_once __DIR__ . '/EmailService.php';

class RegistroService {
    private RegistroRepository $registroRepo;
    private CodigoVerificacionRepository $codigosRepo;
    private EmailService $emailService;

    public function __construct() {
        $this->registroRepo = new RegistroRepository();
        $this->codigosRepo  = new CodigoVerificacionRepository();
        $this->emailService = new EmailService();
    }

    public function registrar(string $nombre, string $correo, string $contrasenna): array {
        if ($this->registroRepo->correoExiste($correo)) {
            return ['ok' => false, 'mensaje' => 'Este correo ya está registrado en el sistema.'];
        }

        $hash = password_hash($contrasenna, PASSWORD_BCRYPT);
        $usuarioId = $this->registroRepo->crearUsuarioPaciente($nombre, $correo, $hash);

        $this->enviarNuevoCodigo($usuarioId, $nombre, $correo);

        return [
            'ok'      => true,
            'mensaje' => 'Cuenta creada. Te enviamos un código de verificación a tu correo.',
            'correo'  => $correo,
        ];
    }

    public function reenviarCodigo(string $correo): array {
        $usuario = $this->codigosRepo->obtenerUsuarioPorCorreo($correo);
        if (!$usuario) {
            return ['ok' => false, 'mensaje' => 'No encontramos una cuenta con ese correo.'];
        }
        if ((int) $usuario['correo_verificado'] === 1) {
            return ['ok' => false, 'mensaje' => 'Este correo ya está verificado.'];
        }

        $this->enviarNuevoCodigo((int) $usuario['id'], $usuario['nombre'], $usuario['correo']);
        return ['ok' => true, 'mensaje' => 'Te enviamos un nuevo código.'];
    }

    public function verificarCodigo(string $correo, string $codigo): array {
        $usuario = $this->codigosRepo->obtenerUsuarioPorCorreo($correo);
        if (!$usuario) {
            return ['ok' => false, 'mensaje' => 'No encontramos una cuenta con ese correo.'];
        }
        if ((int) $usuario['correo_verificado'] === 1) {
            return ['ok' => false, 'mensaje' => 'Este correo ya está verificado.'];
        }

        $registro = $this->codigosRepo->obtenerVigentePorUsuario((int) $usuario['id'], $codigo);
        if (!$registro) {
            return ['ok' => false, 'mensaje' => 'Código incorrecto.'];
        }
        if ((int) $registro['usado'] === 1) {
            return ['ok' => false, 'mensaje' => 'Este código ya fue utilizado. Solicita uno nuevo.'];
        }
        if (strtotime($registro['expira_en']) < time()) {
            return ['ok' => false, 'mensaje' => 'El código expiró. Solicita uno nuevo.'];
        }

        $this->codigosRepo->marcarUsado((int) $registro['id']);
        $this->codigosRepo->marcarCorreoVerificado((int) $usuario['id']);

        return ['ok' => true, 'mensaje' => '¡Correo verificado! Ya puedes iniciar sesión.'];
    }

    private function enviarNuevoCodigo(int $usuarioId, string $nombre, string $correo): void {
        $this->codigosRepo->limpiarCodigosPrevios($usuarioId);

        $codigo   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiraEn = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $this->codigosRepo->crearCodigo($usuarioId, $codigo, $expiraEn);
        $this->emailService->enviarCodigoVerificacion($correo, $nombre, $codigo);
    }
}