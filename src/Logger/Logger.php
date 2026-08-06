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
     * Set a PSR-3 logger for a name (channel). Without a name it becomes the default logger.
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
     * Get a logger by a priority-ordered list of names.
     * Example: getLogger(['rxkk', 'lib']) — tries 'rxkk' first, then 'lib', then falls back to 'default'.
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
     * Check whether a logger with the given name is set (or the default one for null/empty).
     */
    public static function hasLogger(?string $name = null): bool {
        return isset(self::$loggers[self::normalizeName($name)]);
    }

    /**
     * Remove a specific logger (or all of them).
     */
    public static function clear(?string $name = null): void {
        if ($name === null) {
            self::$loggers = [];
            return;
        }
        unset(self::$loggers[self::normalizeName($name)]);
    }

    /**
     * "Template" colored stdout logger
     *
     * @param string $channel Channel name (relevant for Monolog)
     * @param string $level   Minimum level ('debug'..'emergency')
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

        // 3) Fallback: own LineFormatter + a processor that adds the colored level
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%extra.level_colored%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true
        );
        $handler->setFormatter($formatter);

        // Processor for Monolog v3 (LogRecord object)
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
            // Monolog 3: $record is a Monolog\LogRecord (immutable)
            if ($record instanceof \Monolog\LogRecord) {
                $lvl = $record->level->getName(); // e.g. INFO
                $extra = $record->extra;
                $extra['level_colored'] = ($colors[$lvl] ?? '') . $lvl . $reset;
                return $record->with(extra: $extra);
            }
            // Just in case: support for older versions (array)
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

        // Take the level from the argument or from the LOG_LEVEL env var (PSR-3 strings are allowed)
        $minLevel ??= Env::get('LOG_LEVEL') ?: LogLevel::INFO;
        $threshold = self::toLevel($minLevel);

        $handler = new StreamHandler('php://stdout', $threshold, true);
        $handler->setFormatter(new ColorLineFormatter());

        // Enable PSR-3 interpolation of {key} from the context into the message
        $logger->pushProcessor(new PsrLogMessageProcessor());

        $logger->pushHandler($handler);
        return $logger;
    }

    /** Supports PSR-3 strings and integers; defaults to INFO */
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