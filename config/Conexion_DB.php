<?php

require_once __DIR__ . '/env.php';

class Conexion {
    private static ?PDO $instancia = null;

    private function __construct() {}

    public static function obtenConexion(): PDO {
        if (self::$instancia === null) {
            $servidor = $_ENV['DB_HOST'] ?? 'localhost';
            $dbname   = $_ENV['DB_NAME'] ?? 'clinica_dental';
            $username = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASS'] ?? '';

            $dsn = "mysql:host=$servidor;dbname=$dbname;charset=utf8mb4";

            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instancia = new PDO($dsn, $username, $password, $opciones);
            } catch (PDOException $e) {
                die("Error crítico de conexión al sistema: " . $e->getMessage());
            }
        }

        return self::$instancia;
    }
}