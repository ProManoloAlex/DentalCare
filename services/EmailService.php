<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/EmailConfig.php';
require_once __DIR__ . '/../repositories/ConfiguracionCanalesRepository.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private ConfiguracionCanalesRepository $canalesRepo;

    public function __construct() {
        $this->canalesRepo = new ConfiguracionCanalesRepository();
    }

    public function enviar(string $destinatarioCorreo, string $destinatarioNombre, string $asunto, string $cuerpoHtml): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = EmailConfig::$SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = EmailConfig::$SMTP_USER;
            $mail->Password   = EmailConfig::$SMTP_PASS;
            $mail->SMTPSecure = EmailConfig::$SMTP_SECURE === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = EmailConfig::$SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            [$fromEmail, $fromNombre] = $this->obtenerRemitente();
            $mail->setFrom($fromEmail, $fromNombre);
            $mail->addAddress($destinatarioCorreo, $destinatarioNombre);

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpoHtml;
            $mail->AltBody = strip_tags($cuerpoHtml);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('EmailService: fallo al enviar correo — ' . $mail->ErrorInfo);
            return false;
        }
    }

    public function enviarRecuperacionPassword(string $correo, string $nombre, string $token): bool {
        $link   = rtrim(EmailConfig::$APP_URL, '/') . '/auth/restablecer-password.html?token=' . urlencode($token);
        $asunto = 'Recupera tu contraseña — DentalCare';
        $nombreSeguro = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

        $cuerpo = "
            <div style='font-family: Arial, sans-serif; max-width: 480px; margin: 0 auto;'>
                <h2 style='color:#0d9488;'>DentalCare</h2>
                <p>Hola {$nombreSeguro},</p>
                <p>Recibimos una solicitud para restablecer tu contraseña. Si fuiste tú, haz clic en el siguiente botón:</p>
                <p style='text-align:center; margin: 24px 0;'>
                    <a href='{$link}' style='background:#0d9488;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;'>
                        Restablecer contraseña
                    </a>
                </p>
                <p>Este enlace es válido por 1 hora. Si tú no solicitaste este cambio, puedes ignorar este correo.</p>
                <p style='color:#888; font-size: 12px;'>DentalCare — Este es un correo automático, no respondas a este mensaje.</p>
            </div>";

        return $this->enviar($correo, $nombre, $asunto, $cuerpo);
    }

    private function obtenerRemitente(): array {
        $canales = $this->canalesRepo->obtener();
        $correo = $canales['email_remitente'] ?? '';
        $nombre = $canales['email_nombre_remitente'] ?? '';

        return [
            $correo !== '' ? $correo : EmailConfig::$FROM_EMAIL,
            $nombre !== '' ? $nombre : EmailConfig::$FROM_NAME,
        ];
    }
}