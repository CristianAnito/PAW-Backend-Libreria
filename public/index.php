<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\App;

// dirname(__DIR__) = raíz del proyecto (carpeta que contiene app/, config/, public/).
(new App(dirname(__DIR__)))->run();
