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

$logObj = \Rxkk\Lib\Logger\Logger::createLoggerWithStdoutColorConsole('', \Psr\Log\LogLevel::DEBUG);
\Rxkk\Lib\Logger\Logger::setLogger($logObj);
$logger = \Rxkk\Lib\Logger\Logger::getLogger()->withName('test');
$logger->info('my info');
$logger->debug('my debug', ['key' => 'value']);
