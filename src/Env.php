<?php
namespace Rxkk\Lib;

class Env {

    static $ROOT = '';

    public static function setRoot($root) {
        if (!is_dir($root)) {
            throw new \Exception("Root directory '$root' does not exist.");
        }
        $root = realpath($root);
        self::$ROOT = rtrim($root, '/') . '/';
    }

    public static function getRoot() {
        if (self::$ROOT === '') {
            throw new \Exception('Root directory is not set. Use Env::setRoot() to set it.');
        }
        return self::$ROOT;
    }

    public static function get($name, $default = null) {
        return isset($_ENV[$name]) ? $_ENV[$name] : $default;
    }
}
