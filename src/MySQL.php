<?php

namespace Rxkk\Lib;

use mysqli;

class MySQL {

    /** @var mysqli */
    public $connect;

    public function __construct(mysqli $connect) {
        $this->connect = $connect;
    }

    /**
     * you can redefine this method in your class to change credentials
     * @return array
     */
    protected static function getCredentials() {
        return [
            Env::get('MYSQL_HOST'),
            Env::get('MYSQL_USER'),
            Env::get('MYSQL_PASSWORD'),
            Env::get('MYSQL_DATABASE'),
            Env::get('MYSQL_PORT'),
            Env::get('MYSQL_SOCKET'),
            Env::get('MYSQL_FLAG', 0)
        ];
    }

    public function query($sql): array {
//        if (true) {
//            Console::log('SQL: ' . $sql);
//        }

        try {
            $q = $this->connect->query($sql);
        } catch (\Throwable $e) {
            Console::error('SQL error: ' . $e->getMessage() . ' SQL: ' . $sql);
            $q = false;
        }

        // if mysql error - log it
        if ($q === false || $this->connect->errno) {
            Console::error('SQL error 2: ' . $this->connect->error . ' SQL: ' . $sql);
            exit();
        }

        if (is_bool($q)) {
            return [];
        }

        return \mysqli_fetch_all($q, MYSQLI_ASSOC);
    }

    public static function getLastInsertId() {
        return self::getSingleton()->connect->insert_id;
    }

    public static function q($sql): array {
        return self::getSingleton()->query($sql);
    }

    public static function getSingleton() {
        static $instances = [];
        $classname = get_called_class();
        if (!isset($instances[$classname])) {
            $connect = self::getConnect();
            $instances[$classname] = new $classname($connect);
        }
        return $instances[$classname];
    }

    public static function getConnect(): mysqli {
        [$host, $user, $pass, $database, $port, $socket, $flag] = static::getCredentials();

        $mysqli = \mysqli_init();
        $mysqli->real_connect($host, $user, $pass, $database, $port, $socket, $flag);

        if ($mysqli->connect_errno) {
            throw new \Exception('Failed to connect to MySQL: ' . $mysqli->connect_error);
        }

        if (!$mysqli->ping()) {
            throw new \Exception('Failed ping to MySQL: ' . $mysqli->error);
        }

        return $mysqli;
    }
}