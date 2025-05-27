<?php

namespace Rxkk\Lib;

class Console {
    const COLOR_PINK = 95;
    const COLOR_BREEZE = 96;
    const COLOR_BLUE = 34;
    const COLOR_GREEN = 32;
    const COLOR_WITHOUT = null;
    const COLOR_GRAY = 90;

    /**
     * @var mixed
     */
    private static $argv;

    public static function setArguments($argv) {
        self::$argv = $argv;
    }

    public static function getArguments() {
        // get all arguments after 2
        $functionalArgv = array_slice(self::$argv, 2);

        $result = [];

        // example of command: x g::review -p pArg -m mArg
        // in result will be ['p' => 'pArg', 'm' => 'mArg']
        for ($i = 0; $i < count($functionalArgv); $i++) {
            $key = $functionalArgv[$i];
            if (!str_starts_with($key, '-')) {
                continue;
            }

            $key = ltrim($key, '-');

            // if next argument key too - its flag and make true
            if (isset($functionalArgv[$i + 1]) && str_starts_with($functionalArgv[$i + 1], '-')) {
                $result[$key] = true;
                continue;
            }

            $i++;
            $value = trim($functionalArgv[$i]);

            $result[$key] = $value;
        }

        return $result;
    }

    public static function exec($command) {
        echo "\033[0;35m Exec:$command\033[0m\n";
        return shell_exec($command);
    }

    public static function log(string $string, $color = self::COLOR_WITHOUT) {
        if ($color === self::COLOR_WITHOUT) {
            echo "$string\n";
            return;
        }

        echo "\033[0;{$color}m $string\033[0m\n";
    }

    public static function success(string $string) {
        // echo to console with green
        echo "\033[0;32m $string\033[0m\n";
    }

    public static function error(string $string) {
        // echo to console with red
        echo "\033[0;31m $string\033[0m\n";
    }

    public static function warning(string $string) {
        // echo to console with yellow
        echo "\033[0;33m $string\033[0m\n";
    }

    public static function green(string $help) {
        // echo to console with green
        echo "\033[0;32m $help\033[0m\n";
    }

    public static function info(string $string) {
        // echo to console with blue
        echo "\033[0;34m $string\033[0m\n";
    }

    public static function debug(string $string) {
        // echo to console with gray
        echo "\033[0;90m $string\033[0m\n";
    }

    public static function color(string $string, int $color) {
        return "\033[0;{$color}m $string\033[0m";
    }

    public static function getInputMultilines() {
        $lines = file('php://stdin', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // read file by line
        //        $file = Env::getXProjectRoot() . '/input';
        //        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $cleanLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $cleanLines[] = $line;
        }

        return $cleanLines;
    }
}