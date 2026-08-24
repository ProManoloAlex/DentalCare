<?php

require_once __DIR__ . '/env.php';

class EmailConfig {
    public static string $SMTP_HOST;
    public static int    $SMTP_PORT;
    public static string $SMTP_USER;
    public static string $SMTP_PASS;
    public static string $SMTP_SECURE;

    public static string $FROM_EMAIL;
    public static string $FROM_NAME;

    public static string $APP_URL;

    public static function init(): void {
        self::$SMTP_HOST   = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        self::$SMTP_PORT   = (int) ($_ENV['SMTP_PORT'] ?? 587);
        self::$SMTP_USER   = $_ENV['SMTP_USER'] ?? '';
        self::$SMTP_PASS   = $_ENV['SMTP_PASS'] ?? '';
        self::$SMTP_SECURE = $_ENV['SMTP_SECURE'] ?? 'tls';

        self::$FROM_EMAIL  = $_ENV['MAIL_FROM_EMAIL'] ?? self::$SMTP_USER;
        self::$FROM_NAME   = $_ENV['MAIL_FROM_NAME'] ?? 'DentalCare';

        self::$APP_URL     = $_ENV['APP_URL'] ?? 'http://localhost';
    }
}

EmailConfig::init();