<?php

namespace Waypoint\Tests\Integration;

use Waypoint\{Waypoint, JWT};
use Waypoint\Options\{JWTOptions, PermissionOptions};
use Waypoint\Tests\Fixtures\Controllers\{AuthorizationController, ClassAuthenticatedController, ClassRoleController};
use Waypoint\Tests\Fixtures\Support\FakePermissionProvider;

final class AuthorizationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = Waypoint::create();
        $app->configure(function (JWTOptions $opts) {
            $opts->secret = 'test-secret';
        });
        $app->attach([AuthorizationController::class, ClassAuthenticatedController::class, ClassRoleController::class]);
        $app->useJwt();
    }

    public function testARouteWithNoAttributeNeedsNoJwtAtAll(): void
    {
        $output = $this->dispatch('GET', '/authz/public');

        $this->assertSame(200, http_response_code());
        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    public function testAuthenticatedRouteRejectsAMissingJwtWith401(): void
    {
        $output = $this->dispatch('GET', '/authz/authenticated');

        $this->assertSame(401, http_response_code());
        $this->assertSame('Unauthorized', json_decode($output, true)['title'] ?? null);
    }

    public function testAuthenticatedRouteAllowsAValidJwt(): void
    {
        $token = JWT::encode(['sub' => '1']);

        $output = $this->dispatch('GET', '/authz/authenticated', ['Authorization' => "Bearer $token"]);

        $this->assertSame(200, http_response_code());
        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    public function testClassLevelAuthenticatedAppliesToAMethodWithNoAttributeOfItsOwn(): void
    {
        $output = $this->dispatch('GET', '/authz-class/plain');

        $this->assertSame(401, http_response_code());
        $this->assertSame('Unauthorized', json_decode($output, true)['title'] ?? null);
    }

    public function testRoleRouteImpliesAuthenticationAndRejectsAMissingJwtWith401(): void
    {
        $this->dispatch('GET', '/authz/admin-role');

        $this->assertSame(401, http_response_code());
    }

    public function testRoleRouteRejectsAMismatchedRoleWith403(): void
    {
        $token = JWT::encode(['sub' => '1', 'role' => 'user']);

        $output = $this->dispatch('GET', '/authz/admin-role', ['Authorization' => "Bearer $token"]);

        $this->assertSame(403, http_response_code());
        $this->assertSame('Forbidden', json_decode($output, true)['title'] ?? null);
    }

    public function testRoleRouteAllowsAMatchingRole(): void
    {
        $token = JWT::encode(['sub' => '1', 'role' => 'admin']);

        $output = $this->dispatch('GET', '/authz/admin-role', ['Authorization' => "Bearer $token"]);

        $this->assertSame(200, http_response_code());
        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    public function testMethodLevelRoleOverridesTheClassLevelRole(): void
    {
        $editorToken = JWT::encode(['sub' => '1', 'role' => 'editor']);
        $adminToken = JWT::encode(['sub' => '1', 'role' => 'admin']);

        // Class says 'editor'; the method overrides it to 'admin' -- an
        // editor token must now be rejected on this specific route, even
        // though it satisfies every *other* route on the same class.
        $this->dispatch('GET', '/authz-role/override', ['Authorization' => "Bearer $editorToken"]);
        $this->assertSame(403, http_response_code());

        $output = $this->dispatch('GET', '/authz-role/override', ['Authorization' => "Bearer $adminToken"]);
        $this->assertSame(200, http_response_code());
        $this->assertSame(['ok' => true], json_decode($output, true));

        $output = $this->dispatch('GET', '/authz-role/plain', ['Authorization' => "Bearer $editorToken"]);
        $this->assertSame(200, http_response_code());
        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    public function testPermissionsRouteFailsClosedWithNoProviderConfigured(): void
    {
        $token = JWT::encode(['sub' => '1']);

        $output = $this->dispatch('GET', '/authz/manage-users', ['Authorization' => "Bearer $token"]);

        $this->assertSame(403, http_response_code());
        $this->assertSame('Forbidden', json_decode($output, true)['title'] ?? null);
    }

    public function testPermissionsRouteAllowsAGrantedPermission(): void
    {
        $provider = new FakePermissionProvider();
        $provider->granted = ['users.manage'];
        Waypoint::create()->configure(function (PermissionOptions $opts) use ($provider) {
            $opts->provider = $provider;
        });
        $token = JWT::encode(['sub' => '1']);

        $output = $this->dispatch('GET', '/authz/manage-users', ['Authorization' => "Bearer $token"]);

        $this->assertSame(200, http_response_code());
        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    public function testPermissionsRouteRejectsAnUngrantedPermission(): void
    {
        $provider = new FakePermissionProvider();
        $provider->granted = ['something.else'];
        Waypoint::create()->configure(function (PermissionOptions $opts) use ($provider) {
            $opts->provider = $provider;
        });
        $token = JWT::encode(['sub' => '1']);

        $output = $this->dispatch('GET', '/authz/manage-users', ['Authorization' => "Bearer $token"]);

        $this->assertSame(403, http_response_code());
    }

    public function testUnauthenticatedRequestStaysPlainJsonWithNoLoginRedirectUrlConfigured(): void
    {
        // Default JWTOptions::$loginRedirectUrl is null -- a real browser
        // navigation must still get plain 401 Problem Details, not a
        // surprise redirect nothing asked for.
        $output = $this->dispatch('GET', '/authz/authenticated', ['Sec-Fetch-Mode' => 'navigate']);

        $this->assertSame(401, http_response_code());
        $this->assertSame('Unauthorized', json_decode($output, true)['title'] ?? null);
    }

    public function testUnauthenticatedBrowserNavigationRedirectsWhenLoginRedirectUrlIsConfigured(): void
    {
        Waypoint::create()->configure(function (JWTOptions $opts) {
            $opts->loginRedirectUrl = '/login';
        });

        $this->dispatch('GET', '/authz/authenticated', ['Sec-Fetch-Mode' => 'navigate']);

        $this->assertSame(302, http_response_code());
        $this->assertSame('/login', $this->sentHeaders()['location'] ?? null);
    }

    public function testUnauthenticatedFetchCallStillGetsPlainJsonEvenWithLoginRedirectUrlConfigured(): void
    {
        Waypoint::create()->configure(function (JWTOptions $opts) {
            $opts->loginRedirectUrl = '/login';
        });

        // Sec-Fetch-Mode: same-origin is what an ordinary same-origin
        // fetch()/XHR call sends -- never 'navigate' -- so this must fall
        // through to the plain 401 below regardless of the option.
        $output = $this->dispatch('GET', '/authz/authenticated', ['Sec-Fetch-Mode' => 'same-origin']);

        $this->assertSame(401, http_response_code());
        $this->assertSame('Unauthorized', json_decode($output, true)['title'] ?? null);
    }

    public function testUnauthenticatedRequestWithNoSecFetchModeHeaderStaysPlainJson(): void
    {
        Waypoint::create()->configure(function (JWTOptions $opts) {
            $opts->loginRedirectUrl = '/login';
        });

        // No Sec-Fetch-Mode at all (an older browser, or a non-browser
        // client like curl) -- treated as "not a navigation", the safe
        // default.
        $output = $this->dispatch('GET', '/authz/authenticated');

        $this->assertSame(401, http_response_code());
        $this->assertSame('Unauthorized', json_decode($output, true)['title'] ?? null);
    }

    public function testMultiplePermissionsAllMustBeGranted(): void
    {
        $provider = new FakePermissionProvider();
        $provider->granted = ['users.manage']; // missing 'users.delete'
        Waypoint::create()->configure(function (PermissionOptions $opts) use ($provider) {
            $opts->provider = $provider;
        });
        $token = JWT::encode(['sub' => '1']);

        $this->dispatch('GET', '/authz/multi-permission', ['Authorization' => "Bearer $token"]);
        $this->assertSame(403, http_response_code());

        $provider->granted = ['users.manage', 'users.delete'];
        $output = $this->dispatch('GET', '/authz/multi-permission', ['Authorization' => "Bearer $token"]);
        $this->assertSame(200, http_response_code());
        $this->assertSame(['ok' => true], json_decode($output, true));
    }

    /** @return array<string, string> */
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
