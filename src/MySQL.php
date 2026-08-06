<?php

namespace Rxkk\Lib;

use mysqli;
use Rxkk\Lib\Logger\Logger;

class MySQL {

    /**
     * After this many seconds of inactivity the connection is health-checked before the next
     * query (see {@see refreshConnect()}). 0 — never health-check.
     */
    protected const IDLE_TIMEOUT = 10;

    /**
     * A connection older than this is recreated regardless of its state
     * (see {@see refreshConnect()}). 0 — let it live forever.
     */
    protected const MAX_LIFETIME = 900;

    /** @var mysqli */
    public $connect;

    private \Monolog\Logger $logger;

    /** When the last query finished — the starting point for measuring idle time. */
    private float $lastQueryAt;

    /** When the current connection was opened — the starting point for measuring its lifetime. */
    private float $connectedAt;

    public function __construct(mysqli $connect) {
        $this->logger = Logger::getLogger()->withName('MySQL');
        $this->connect = $connect;
        $this->connectedAt = microtime(true);
        $this->lastQueryAt = $this->connectedAt;
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

    /**
     * Errors are not swallowed here: a broken query throws mysqli_sql_exception (or the
     * RuntimeException below when mysqli reporting is off) so the caller sees the real reason.
     */
    public function query($sql): array {
        $this->logger->debug('SQL: ' . $sql);

        $this->refreshConnect();

        try {
            $q = $this->connect->query($sql);
        } finally {
            // Idle time is measured from the end of the query, not the start: otherwise a long
            // query would "age" itself and the next call would health-check a busy connection.
            $this->lastQueryAt = microtime(true);
        }

        // Reached only without MYSQLI_REPORT_STRICT — with it, query() throws instead of
        // returning false.
        if ($q === false) {
            $error = $this->connect->error;
            $this->logger->error('SQL error: ' . $error . ' SQL: ' . $sql);
            throw new \RuntimeException('SQL error: ' . $error . ' | SQL: ' . $sql);
        }

        if (is_bool($q)) {
            return [];
        }

        return \mysqli_fetch_all($q, MYSQLI_ASSOC);
    }

    /**
     * Make sure the connection is usable before running a query.
     *
     * Why: {@see getSingleton()} caches the connection for the whole lifetime of the process. For
     * CLI that goes unnoticed (the process lives for seconds), but long-running processes — the
     * MCP server, daemons, workers — keep it for hours. An idle connection gets closed by the
     * server (wait_timeout) or dropped by the network/VPN/NAT, and the cached handle stays dead
     * forever: every later query fails with "MySQL server has gone away" until a process restart.
     *
     * Two rules, cheapest first:
     *  - older than {@see MAX_LIFETIME} — recreate it without asking. Bounds how long a single
     *    connection can accumulate problems;
     *  - idle for longer than {@see IDLE_TIMEOUT} — health-check it and recreate only if it is
     *    dead. One round-trip, and only after a pause: inside a loop of queries there is no
     *    overhead at all.
     *
     * A connection can still die between the health check and the query. That is not handled: the
     * query fails with the real mysqli error and the caller decides what to do about it.
     */
    protected function refreshConnect(): void {
        $now = microtime(true);

        $maxLifetime = static::getMaxLifetime();
        if ($maxLifetime > 0 && $now - $this->connectedAt >= $maxLifetime) {
            $this->logger->debug(sprintf(
                'MySQL connection age %.1fs >= %.1fs — reconnecting',
                $now - $this->connectedAt,
                $maxLifetime
            ));
            $this->reconnect();

            return;
        }

        $idleTimeout = static::getIdleTimeout();
        if ($idleTimeout <= 0 || $now - $this->lastQueryAt < $idleTimeout) {
            return;
        }

        if ($this->isAlive()) {
            return;
        }

        $this->logger->warning('MySQL connection is dead after an idle period — reconnecting');
        $this->reconnect();
    }

    /**
     * Health check. A plain query instead of mysqli::ping(), which is deprecated as of PHP 8.4.
     */
    protected function isAlive(): bool {
        try {
            $result = $this->connect->query('SELECT 1');
        } catch (\Throwable $e) {
            $this->logger->debug('MySQL health check failed: ' . $e->getMessage());

            return false;
        }

        if ($result instanceof \mysqli_result) {
            $result->free();
        }

        return $result !== false;
    }

    /**
     * Idle threshold in seconds. Override it in a subclass or set MYSQL_IDLE_TIMEOUT in the env.
     */
    protected static function getIdleTimeout(): float {
        return (float)Env::get('MYSQL_IDLE_TIMEOUT', static::IDLE_TIMEOUT);
    }

    /**
     * Connection lifetime in seconds. Override it in a subclass or set MYSQL_MAX_LIFETIME in the env.
     */
    protected static function getMaxLifetime(): float {
        return (float)Env::get('MYSQL_MAX_LIFETIME', static::MAX_LIFETIME);
    }

    /**
     * Recreate the connection. Replaces the handle inside the current instance, so the singleton
     * is repaired as a whole.
     *
     * Beware: session state (USE, SET SESSION, temporary tables, user variables, an open
     * transaction) does not exist on the new connection.
     */
    public function reconnect(): mysqli {
        $old = $this->connect;

        $this->connect = static::getConnect();
        $this->connectedAt = microtime(true);
        $this->lastQueryAt = $this->connectedAt;

        // Close the old one afterwards — if the new one fails to open, the instance keeps the old
        // handle. close() on an already dead connection may complain, that is no reason to fail.
        try {
            $old->close();
        } catch (\Throwable $e) {
            $this->logger->debug('Old MySQL connection close failed: ' . $e->getMessage());
        }

        return $this->connect;
    }

    public static function getLastInsertId() {
        return self::getSingleton()->connect->insert_id;
    }

    public static function q($sql): array {
        return self::getSingleton()->query($sql);
    }

    /**
     * @return self
     */
    public static function getSingleton() {
        static $instances = [];
        $classname = get_called_class();
        if (!isset($instances[$classname])) {
            // static::, not self:: — a subclass may override getConnect() (its own credentials
            // format / a different host), and self:: would silently call the base implementation.
            $connect = static::getConnect();
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

        // ping() was redundant here (real_connect already reported success) and is deprecated
        // as of PHP 8.4

        return $mysqli;
    }
}