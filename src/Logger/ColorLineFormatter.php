<?php

namespace Rxkk\Lib\Logger;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class ColorLineFormatter implements FormatterInterface {
    /** @var array<string,string> */
    private array $colors = [
        'debug'     => "\033[90m", // gray
        'info'      => "\033[34m", // blue - reporting a completed action
        'notice'    => "\033[32m", // green - reporting a successful result
        'warning'   => "\033[33m", // yellow
        'error'     => "\033[31m", // red
        'critical'  => "\033[35m", // magenta
        'alert'     => "\033[95m", // bright magenta
        'emergency' => "\033[91m", // bright red
    ];
    private string $reset = "\033[0m";

    public function format(LogRecord $record): string {
        $ts        = $record->datetime->format('Y-m-d H:i:s');
        $levelName = strtoupper($record->level->getName()); // DEBUG/INFO/...
        // The message is already interpolated by PsrLogMessageProcessor (see the factory below)
        $msg       = (string) $record->message;

//        $normalizedCtx = $this->normalize($record->context);
//        $ctx = empty($normalizedCtx)
//            ? ''
//            : ' ' . json_encode(
//                $normalizedCtx,
//                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
//            );

        if ($record->context) {
            $ctx = var_export($record->context, true);
            $line = sprintf("[%s] %s.%s: %s\n%s\n", $ts, $record->channel, $levelName, $msg, $ctx);
        } else {
            $line = sprintf("[%s] %s.%s: %s\n", $ts, $record->channel, $levelName, $msg);
        }

        $color = $this->colors[strtolower($levelName)] ?? '';
        return $color . $line . $this->reset;
    }

    /** @param LogRecord[] $records */
    public function formatBatch(array $records): string  {
        return implode('', array_map([$this, 'format'], $records));
    }

    /** Convert the context into a serializable form, same as in interpolate() */
    private function normalize(mixed $val): mixed {
        if ($val instanceof \Throwable) {
            return $val->getMessage();
        }
        if (is_null($val) || is_scalar($val)) {
            return $val;
        }
        if (is_object($val) && method_exists($val, '__toString')) {
            return (string) $val;
        }
        if (is_array($val)) {
            foreach ($val as $k => $v) {
                $val[$k] = $this->normalize($v);
            }
            return $val;
        }
        if (is_object($val)) {
            return ['class' => get_class($val)];
        }
        return $val;
    }
}
