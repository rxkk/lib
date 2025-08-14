<?php

namespace Rxkk\Lib\Logger;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class ColorLineFormatter implements FormatterInterface {
    /** @var array<string,string> */
    private array $colors = [
        'debug'     => "\033[90m", // серый
        'info'      => "\033[34m", // синий - говорим о совершенном
        'notice'    => "\033[32m", // зелёный - говорим об успешном результате
        'warning'   => "\033[33m", // жёлтый
        'error'     => "\033[31m", // красный
        'critical'  => "\033[35m", // пурпурный
        'alert'     => "\033[95m", // ярко-пурпурный
        'emergency' => "\033[91m", // ярко-красный
    ];
    private string $reset = "\033[0m";

    public function format(LogRecord $record): string {
        $ts        = $record->datetime->format('Y-m-d H:i:s');
        $levelName = strtoupper($record->level->getName()); // DEBUG/INFO/...
        // Сообщение уже интерполировано PsrLogMessageProcessor (см. фабрику ниже)
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

    /** Приводим контекст к сериализуемому виду, как у тебя в interpolate() */
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
