<?php

declare(strict_types=1);

// Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

// Dispatch
Flight::start();
