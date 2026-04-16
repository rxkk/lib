<?php

namespace Rxkk\Lib;

use Rxkk\Lib\Logger\Logger;

class Curl
{
    private static function logger(): \Monolog\Logger
    {
        // Uses 'Curl' logger when set; falls back to 'default'.
        // Override via Logger::setLogger($logger, 'Curl') to route curl logs separately.
        return Logger::getLogger(['Curl', 'default']);
    }

    public static function get(string $url, array $options = []): CurlResponse
    {
        return self::request('GET', $url, $options);
    }

    public static function post(string $url, array $options = []): CurlResponse
    {
        return self::request('POST', $url, $options);
    }

    public static function put(string $url, array $options = []): CurlResponse
    {
        return self::request('PUT', $url, $options);
    }

    public static function patch(string $url, array $options = []): CurlResponse
    {
        return self::request('PATCH', $url, $options);
    }

    public static function delete(string $url, array $options = []): CurlResponse
    {
        return self::request('DELETE', $url, $options);
    }

    public static function head(string $url, array $options = []): CurlResponse
    {
        return self::request('HEAD', $url, $options);
    }

    /**
     * Universal HTTP request via cURL.
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS, …)
     * @param string $url    Request URL
     * @param array{
     *   json?:             array<mixed>,
     *   form?:             array<mixed>,
     *   multipart?:        array<mixed>,
     *   body?:             string,
     *   query?:            array<string, mixed>,
     *   headers?:          string[],
     *   timeout?:          int,
     *   connect_timeout?:  int,
     *   ssl_verify?:       bool,
     *   follow_redirects?: bool,
     *   basic_auth?:       array{string, string},
     *   curl_opts?:        array<int, mixed>,
     * } $options Options:
     *   - json             — encode array as JSON body; auto-sets Content-Type: application/json
     *   - form             — encode array as URL-encoded body; auto-sets Content-Type: application/x-www-form-urlencoded
     *   - multipart        — array for multipart/form-data; use \CURLFile values for file uploads
     *   - body             — raw request body string
     *   - query            — key-value pairs appended to the URL as query string
     *   - headers          — extra request headers, e.g. ['Authorization: Bearer token']
     *   - timeout          — total transfer timeout in seconds (default: 30)
     *   - connect_timeout  — connection timeout in seconds (default: 10)
     *   - ssl_verify       — verify SSL certificate and host (default: true)
     *   - follow_redirects — follow Location redirects (default: true)
     *   - basic_auth       — [username, password] for HTTP Basic Auth
     *   - curl_opts        — raw CURLOPT_* overrides, e.g. [CURLOPT_PROXY => 'http://proxy:8080']
     */
    public static function request(string $method, string $url, array $options = []): CurlResponse
    {
        $method = strtoupper($method);

        if (!empty($options['query'])) {
            $sep  = str_contains($url, '?') ? '&' : '?';
            $url .= $sep . http_build_query($options['query']);
        }

        $headers    = $options['headers'] ?? [];
        $postFields = null;

        if (isset($options['multipart'])) {
            // Passing an array lets cURL set multipart/form-data with its own boundary.
            // Use \CURLFile instances inside the array for file uploads.
            $postFields = $options['multipart'];
        } elseif (isset($options['json'])) {
            $postFields = json_encode(
                $options['json'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            if (!self::hasHeader('Content-Type', $headers)) {
                $headers[] = 'Content-Type: application/json';
            }
        } elseif (isset($options['form'])) {
            $postFields = http_build_query($options['form']);
            if (!self::hasHeader('Content-Type', $headers)) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
        } elseif (isset($options['body'])) {
            $postFields = $options['body'];
        }

        $responseHeaders = [];
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_FOLLOWLOCATION => $options['follow_redirects'] ?? true,
            CURLOPT_TIMEOUT        => $options['timeout']          ?? 30,
            CURLOPT_CONNECTTIMEOUT => $options['connect_timeout']  ?? 10,
            CURLOPT_SSL_VERIFYPEER => $options['ssl_verify']       ?? true,
            CURLOPT_SSL_VERIFYHOST => ($options['ssl_verify'] ?? true) ? 2 : 0,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $len = strlen($line);
                if (str_contains($line, ':')) {
                    [$name, $value]        = explode(':', $line, 2);
                    $responseHeaders[trim($name)] = trim($value);
                }
                return $len;
            },
        ]);

        if (isset($options['basic_auth'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $options['basic_auth'][0] . ':' . $options['basic_auth'][1]);
        }

        if ($postFields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if (!empty($options['curl_opts'])) {
            curl_setopt_array($ch, $options['curl_opts']);
        }

        self::logger()->debug('Curl request', [
            'method'  => $method,
            'url'     => $url,
            'headers' => $headers,
            'body'    => is_array($postFields) ? '[multipart/form-data]' : $postFields,
        ]);

        $raw       = curl_exec($ch);
        $info      = curl_getinfo($ch);
        $curlErrno = curl_errno($ch);
        $curlError = $curlErrno ? curl_error($ch) : null;
        curl_close($ch);

        $bodyRaw = ($raw === false) ? '' : (string)$raw;

        self::logger()->debug('Curl response', [
            'http_code'    => $info['http_code'],
            'content_type' => $info['content_type'] ?? null,
            'total_time'   => $info['total_time'],
            'body'         => $bodyRaw,
            'curl_error'   => $curlError,
        ]);

        if ($curlError !== null) {
            return new CurlResponse(
                statusCode: 0,
                raw:        $bodyRaw,
                data:       null,
                info:       $info,
                headers:    $responseHeaders,
                error:      $curlError,
            );
        }

        $decoded = json_decode($bodyRaw, true);

        return new CurlResponse(
            statusCode: (int)$info['http_code'],
            raw:        $bodyRaw,
            data:       $decoded ?? $bodyRaw,
            info:       $info,
            headers:    $responseHeaders,
        );
    }

    private static function hasHeader(string $name, array $headers): bool
    {
        $prefix = strtolower($name) . ':';
        foreach ($headers as $h) {
            if (str_starts_with(strtolower($h), $prefix)) {
                return true;
            }
        }
        return false;
    }
}
