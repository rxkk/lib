<?php

use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$envDir = __DIR__ . '/../';
$envPath = $envDir . '.env';
if (file_exists($envPath)) {
    $dotenv = Dotenv::createImmutable($envDir);
    $dotenv->load();
}

\Rxkk\Lib\Console::success('test success');
