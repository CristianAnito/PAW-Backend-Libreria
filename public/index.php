<?php
declare(strict_types=1);

// Front controller: única puerta de entrada de todas las peticiones (ver .htaccess).
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\App;

// dirname(__DIR__) = raíz del proyecto (carpeta que contiene app/, config/, public/).
$app = new App(dirname(__DIR__));
$app->run();
