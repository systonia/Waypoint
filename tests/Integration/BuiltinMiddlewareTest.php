<?php

namespace Waypoint\Tests\Integration;

use Waypoint\{Waypoint, JWT};
use Waypoint\Options\{JWTOptions, CorsOptions};
use Waypoint\Tests\Fixtures\Controllers\{OrdersController, WhoAmIController};

final class BuiltinMiddlewareTest extends IntegrationTestCase
{
    public function testCorsMiddlewareAddsHeadersAndPassesNonOptionsRequestsThrough(): void
    {
        $app = Waypoint::create();
        $app->attach([OrdersController::class]);
        $app->configure(function (CorsOptions $opts) {
            $opts->allowOrigin = 'https://example.com';
            $opts->allowMethods = 'GET, POST';
            $opts->allowHeaders = 'Content-Type';
            $opts->allowCredentials = true;
        });
        $app->useCors();

        $output = $this->dispatch('POST', '/orders');

        $this->assertSame('"pong"', $output);
        $headers = $this->sentHeaders();
        $this->assertSame('https://example.com', $headers['access-control-allow-origin'] ?? null);
        $this->assertSame('GET, POST', $headers['access-control-allow-methods'] ?? null);
        $this->assertSame('Content-Type', $headers['access-control-allow-headers'] ?? null);
        $this->assertSame('true', $headers['access-control-allow-credentials'] ?? null);
    }

    public function testCorsMiddlewareShortCircuitsOptionsPreflightWith204(): void
    {
        $app = Waypoint::create();
        $app->attach([OrdersController::class]);
        $app->configure(function (CorsOptions $opts) {
            $opts->allowOrigin = '*';
        });
        $app->useCors();

        $this->dispatch('OPTIONS', '/orders');

        $this->assertSame(204, http_response_code());
        $this->assertSame('*', $this->sentHeaders()['access-control-allow-origin'] ?? null);
    }

    public function testCorsMiddlewareOmitsEveryHeaderWhenUnconfigured(): void
    {
        $app = Waypoint::create();
        $app->attach([OrdersController::class]);
        $app->useCors();

        $this->dispatch('POST', '/orders');

        $headers = $this->sentHeaders();
        foreach (['access-control-allow-origin', 'access-control-allow-methods', 'access-control-allow-headers', 'access-control-allow-credentials'] as $name) {
            $this->assertArrayNotHasKey($name, $headers);
        }
    }

    public function testCorsMiddlewareReflectsConfigureCallsMadeAfterUseCors(): void
    {
        // Resolved fresh from the container on every request -- configure()
        // doesn't have to run before useCors() itself.
        $app = Waypoint::create();
        $app->attach([OrdersController::class]);
        $app->useCors();
        $app->configure(function (CorsOptions $opts) {
            $opts->allowOrigin = 'https://example.com';
        });

        $this->dispatch('POST', '/orders');

        $this->assertSame('https://example.com', $this->sentHeaders()['access-control-allow-origin'] ?? null);
    }

    public function testJwtMiddlewareAttachesDecodedPayloadFromAuthorizationHeader(): void
    {
        $app = Waypoint::create();
        $app->configure(function (JWTOptions $opts) {
            $opts->secret = 'test-secret';
        });
        $token = JWT::encode(['sub' => '99']);

        $app->attach([WhoAmIController::class]);
        $app->useJwt();

        $output = $this->dispatch('GET', '/whoami', ['Authorization' => "Bearer $token"]);

        $this->assertSame('99', json_decode($output, true)['jwt']['sub']);
    }

    public function testJwtMiddlewareLeavesJwtNullWithoutAnAuthorizationHeader(): void
    {
        $app = Waypoint::create();
        $app->configure(function (JWTOptions $opts) {
            $opts->secret = 'test-secret';
        });
        $app->attach([WhoAmIController::class]);
        $app->useJwt();

        $output = $this->dispatch('GET', '/whoami');

        $this->assertNull(json_decode($output, true)['jwt']);
    }

    public function testJwtMiddlewareFallsBackToServerAuthorizationSuperglobal(): void
    {
        $app = Waypoint::create();
        $app->configure(function (JWTOptions $opts) {
            $opts->secret = 'test-secret';
        });
        $token = JWT::encode(['sub' => '7']);
        $app->attach([WhoAmIController::class]);
        $app->useJwt();

        $_SERVER['AUTHORIZATION'] = "Bearer $token";
        try {
            $output = $this->dispatch('GET', '/whoami');
        } finally {
            unset($_SERVER['AUTHORIZATION']);
        }

        $this->assertSame('7', json_decode($output, true)['jwt']['sub']);
    }

    public function testJwtMiddlewareFallsBackToTheConfiguredCookieWithoutAnAuthorizationHeader(): void
    {
        $app = Waypoint::create();
        $app->configure(function (JWTOptions $opts) {
            $opts->secret = 'test-secret';
            $opts->cookieName = 'auth_token';
        });
        $token = JWT::encode(['sub' => '42']);
        $app->attach([WhoAmIController::class]);
        $app->useJwt();

        $_COOKIE['auth_token'] = $token;
        try {
            $output = $this->dispatch('GET', '/whoami');
        } finally {
            unset($_COOKIE['auth_token']);
        }

        $this->assertSame('42', json_decode($output, true)['jwt']['sub']);
    }

    public function testJwtMiddlewarePrefersTheAuthorizationHeaderOverTheCookie(): void
    {
        $app = Waypoint::create();
        $app->configure(function (JWTOptions $opts) {
            $opts->secret = 'test-secret';
            $opts->cookieName = 'auth_token';
        });
        $headerToken = JWT::encode(['sub' => '1']);
        $cookieToken = JWT::encode(['sub' => '2']);
        $app->attach([WhoAmIController::class]);
        $app->useJwt();

        $_COOKIE['auth_token'] = $cookieToken;
        try {
            $output = $this->dispatch('GET', '/whoami', ['Authorization' => "Bearer $headerToken"]);
        } finally {
            unset($_COOKIE['auth_token']);
        }

        $this->assertSame('1', json_decode($output, true)['jwt']['sub']);
    }

    public function testJwtMiddlewareIgnoresTheCookieWhenCookieNameIsNotConfigured(): void
    {
        $app = Waypoint::create();
        $app->configure(function (JWTOptions $opts) {
            $opts->secret = 'test-secret';
        });
        $token = JWT::encode(['sub' => '5']);
        $app->attach([WhoAmIController::class]);
        $app->useJwt();

        $_COOKIE['auth_token'] = $token;
        try {
            $output = $this->dispatch('GET', '/whoami');
        } finally {
            unset($_COOKIE['auth_token']);
        }

        $this->assertNull(json_decode($output, true)['jwt']);
    }

    public function testJwtMiddlewareIgnoresAnInvalidCookieToken(): void
    {
        $app = Waypoint::create();
        $app->configure(function (JWTOptions $opts) {
            $opts->secret = 'test-secret';
            $opts->cookieName = 'auth_token';
        });
        $app->attach([WhoAmIController::class]);
        $app->useJwt();

        $_COOKIE['auth_token'] = 'not-a-valid-jwt';
        try {
            $output = $this->dispatch('GET', '/whoami');
        } finally {
            unset($_COOKIE['auth_token']);
        }

        $this->assertNull(json_decode($output, true)['jwt']);
    }

    private function sentHeaders(): array
    {
        $raw = function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
        $headers = [];
        foreach ($raw as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2) + [1 => '']);
            $headers[strtolower($name)] = $value;
        }
        return $headers;
    }
}
