<?php

namespace Rxkk\Lib;

class CurlResponse
{
    /**
     * @param int                  $statusCode HTTP status code (0 if cURL-level error occurred)
     * @param string               $raw        Raw response body
     * @param array|string|null    $data       JSON-decoded body, or raw string if the body is not JSON
     * @param array<string, mixed> $info       curl_getinfo() result
     * @param array<string, string> $headers   Parsed response headers
     * @param string|null          $error      cURL error message, or null when there is no error
     */
    public function __construct(
        public readonly int               $statusCode,
        public readonly string            $raw,
        public readonly array|string|null $data,
        public readonly array             $info,
        public readonly array             $headers = [],
        public readonly ?string           $error   = null,
    ) {}

    /** True when there is no cURL error and HTTP status is 2xx. */
    public function isSuccess(): bool
    {
        return $this->error === null && $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isOk(): bool
    {
        return $this->isSuccess();
    }
}
