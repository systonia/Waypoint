<?php

namespace Waypoint\Http;

/**
 * Undocumented class
 */
class Request
{
    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $method;

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $path;

    /**
     * @var array<string, string>
     */
    public array $headers = [];

    /**
     * @var array<string, mixed>
     */
    public array $query = [];

    /**
     * @var array<string, mixed>
     */
    public array $body = [];

    /**
     * Undocumented variable
     *
     * @var mixed
     */
    public mixed $jwt = null;

    /**
     * True if the client accepts a partial response (AJAX navigation)
     *
     * @var bool
     */
    public bool $acceptPartial = false;

    /**
     * Undocumented function
     */
    private function __construct()
    {
    }

    /**
     * Capture the current HTTP request from globals.
     *
     * @param string|null $rawBody Override for the raw request body instead
     *  of reading php://input -- mainly so tests can exercise JSON body
     *  parsing, since php://input is always empty under a CLI test runner.
     * @return self
     */
    public static function capture(?string $rawBody = null): self
    {
        $req = new self();
        $req->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $req->path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Headers (SAPI-agnostic)
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
                );
                $req->headers[$name] = $value;
            }
        }

        $req->query = $_GET;

        // Parse JSON or form data, prefer JSON if present
        $raw = $rawBody ?? file_get_contents('php://input');
        $req->body = [];
        if ($raw && ($data = json_decode($raw, true))) {
            $req->body = $data;
        } elseif ($_POST) {
            $req->body = $_POST;
        }

        return $req;
    }

    /**
     * Undocumented function
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function query(string $key, $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Undocumented function
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function body(?string $key = null, $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    /**
     * Undocumented function
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, $default = null): mixed
    {
        return $this->body($key, $default);
    }
}
