<?php

namespace Rxkk\Lib\Logger;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LogLevel;
use Rxkk\Lib\Env;

class Logger {
    /** @var array<string, \Monolog\Logger> */
    private static array $loggers = [];

    /**
     * Установить PSR-3 логгер для имени (канала). Без имени — логгер по умолчанию.
     */
    public static function setLogger(\Monolog\Logger $logger, ?string $name = null): void {
        $key = self::normalizeName($name);
        self::$loggers[$key] = $logger;
    }

    private static function normalizeName(?string $name): string {
        $name = trim((string)($name ?? ''));
        return $name === '' ? 'default' : strtolower($name);
    }

    /**
     * Получить логгер по приоритетному списку имён.
     * Пример: getLogger(['rxkk', 'lib']) — сначала 'rxkk', потом 'lib', затем fallback 'default'.
     */
    public static function getLogger(string|array|null $names = null): \Monolog\Logger {
        $candidates = is_array($names) ? $names : (is_string($names) ? [$names] : ['default']);

        foreach ($candidates as $name) {
            $key = self::normalizeName($name);
            if (isset(self::$loggers[$key])) {
                return self::$loggers[$key];
            }
        }

        $logger = self::createLoggerNull();
        return self::$loggers['default'] = $logger;
    }

    /**
     * Проверить, установлен ли логгер с данным именем (или default при null/пустом).
     */
    public static function hasLogger(?string $name = null): bool {
        return isset(self::$loggers[self::normalizeName($name)]);
    }

    /**
     * Удалить конкретный логгер (или все).
     */
    public static function clear(?string $name = null): void {
        if ($name === null) {
            self::$loggers = [];
            return;
        }
        unset(self::$loggers[self::normalizeName($name)]);
    }

    /**
     * «Темплейтный» цветной stdout-логгер
     *
     * @param string $channel Имя канала (актуально для Monolog)
     * @param string $level   Минимальный уровень ('debug'..'emergency')
     */
    public static function getNewLoggerWithStdoutColorConsole(
        string $channel,
        ?string $level = null
    ): \Psr\Log\LoggerInterface {
        $level ??= Env::get('LOG_LEVEL', 'debug');

        // 1) Monolog logger + handler
        $monolog  = new \Monolog\Logger($channel);
        $minLevel = Level::fromName(strtoupper($level));
        $handler  = new StreamHandler('php://stdout', $minLevel);

        // 3) Фоллбек: свой LineFormatter + процессор, который добавляет цветной уровень
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%extra.level_colored%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true
        );
        $handler->setFormatter($formatter);

        // Процессор для Monolog v3 (LogRecord-объект)
        $colors = [
            'DEBUG'     => "\033[36m", // cyan
            'INFO'      => "\033[32m", // green
            'NOTICE'    => "\033[34m", // blue
            'WARNING'   => "\033[33m", // yellow
            'ERROR'     => "\033[31m", // red
            'CRITICAL'  => "\033[35m", // magenta
            'ALERT'     => "\033[95m", // bright magenta
            'EMERGENCY' => "\033[91m", // bright red
        ];
        $reset = "\033[0m";

        $monolog->pushProcessor(function ($record) use ($colors, $reset) {
            // Monolog 3: $record — это Monolog\LogRecord (иммутабельный)
            if ($record instanceof \Monolog\LogRecord) {
                $lvl = $record->level->getName(); // e.g. INFO
                $extra = $record->extra;
                $extra['level_colored'] = ($colors[$lvl] ?? '') . $lvl . $reset;
                return $record->with(extra: $extra);
            }
            // На всякий случай: поддержка старых версий (массив)
            $lvl = $record['level_name'] ?? 'INFO';
            $record['extra']['level_colored'] = ($colors[$lvl] ?? '') . $lvl . $reset;
            return $record;
        });

        $monolog->pushHandler($handler);
        $monolog->pushProcessor(new PsrLogMessageProcessor());

        return $monolog;
    }

    public static function createLoggerWithStdoutColorConsole(string $channel = '', string|int|null $minLevel = null): \Monolog\Logger
    {
        $logger = new \Monolog\Logger($channel);

        // Берём уровень из аргумента или из окружения LOG_LEVEL (PSR-3 строки допустимы)
        $minLevel ??= Env::get('LOG_LEVEL') ?: LogLevel::INFO;
        $threshold = self::toLevel($minLevel);

        $handler = new StreamHandler('php://stdout', $threshold, true);
        $handler->setFormatter(new ColorLineFormatter());

        // Включаем PSR-3 интерполяцию {key} из контекста в message
        $logger->pushProcessor(new PsrLogMessageProcessor());

        $logger->pushHandler($handler);
        return $logger;
    }

    /** Поддержка строк PSR-3 и чисел; по умолчанию INFO */
    private static function toLevel(string|int $level): Level
    {
        if (is_int($level)) {
            return Level::fromValue($level);
        }
        return match (strtolower($level)) {
            'debug'     => Level::Debug,
            'info'      => Level::Info,
            'notice'    => Level::Notice,
            'warning'   => Level::Warning,
            'error'     => Level::Error,
            'critical'  => Level::Critical,
            'alert'     => Level::Alert,
            'emergency' => Level::Emergency,
            default     => Level::Info,
        };
    }

    private static function createLoggerNull() {
        $logger = new \Monolog\Logger('null');
        $logger->pushHandler(new NullHandler());
        return $logger;
    }
}