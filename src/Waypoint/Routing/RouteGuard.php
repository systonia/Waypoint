<?php

namespace Waypoint\Routing;

use Waypoint\Container;
use Waypoint\Csrf;
use Waypoint\Enums\Message;
use Waypoint\Exceptions\{ForbiddenException, UnauthorizedException};
use Waypoint\Http\Request;
use Waypoint\Options\PermissionOptions;

/**
 * The per-request access checks a compiled route carries: #[Authenticated]/
 * #[Role]/#[Permissions] (folded into 'authenticated'/'role'/'permissions'
 * by RouteCompiler) and CSRF verification for state-changing methods
 * unless #[SkipCsrf]. A plan predating a key defaults to the safe side:
 * no auth requirement, but CSRF checked.
 */
final class RouteGuard
{
    private const CSRF_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private ?Container $container)
    {
    }

    /** @param array<string, mixed> $route */
    public function check(array $route, string $httpMethod, Request $req): void
    {
        if ($route['authenticated'] ?? false) {
            $this->checkAuthentication($route, $req);
        }

        if (
            in_array(strtoupper($httpMethod), self::CSRF_METHODS, true)
            && !($route['skipCsrf'] ?? false)
            && $this->container !== null
            && !$this->container->get(Csrf::class)->verify($req)
        ) {
            throw new ForbiddenException(Message::CsrfTokenInvalid->value);
        }
    }

    /** @param array<string, mixed> $route */
    private function checkAuthentication(array $route, Request $req): void
    {
        if ($req->jwt === null) {
            throw new UnauthorizedException();
        }

        $role = $route['role'] ?? null;
        if (is_string($role) && (!is_array($req->jwt) || ($req->jwt['role'] ?? null) !== $role)) {
            throw new ForbiddenException();
        }

        $permissions = $route['permissions'] ?? null;
        if (!is_array($permissions) || $permissions === []) {
            return;
        }
        // No provider configured fails closed.
        $provider = $this->container?->get(PermissionOptions::class)->provider;
        foreach ($permissions as $permission) {
            if (!is_string($permission) || $provider === null || !$provider->hasPermission($req->jwt, $permission)) {
                throw new ForbiddenException();
            }
        }
    }
}
