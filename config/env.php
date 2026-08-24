<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Carga las variables de .env a $_ENV. safeLoad() no truena si el archivo
// no existe -- útil en un servidor real donde las variables se configuran
// directo en el entorno del sistema, sin necesidad de un archivo .env.
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();