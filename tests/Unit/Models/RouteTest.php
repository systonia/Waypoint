<?php

namespace Waypoint\Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use Waypoint\Models\Route;

final class RouteTest extends TestCase
{
    public function testNormalizesHttpMethodToUppercase(): void
    {
        $route = new Route('get', '/users', ['Controller', 'index']);
        $this->assertSame('GET', $route->method);
    }

    public function testNormalizesPathToHaveLeadingSlashAndNoTrailingSlash(): void
    {
        $route = new Route('GET', 'users/{id}/', ['Controller', 'show']);
        $this->assertSame('/users/{id}', $route->rawPath);
    }

    public function testExtractsPlaceholderNamesInOrder(): void
    {
        $route = new Route('GET', '/users/{id}/orders/{orderId}', ['Controller', 'orders']);
        $this->assertSame(['id', 'orderId'], $route->paramNames);
    }

    public function testBuildsARegexThatMatchesARealPath(): void
    {
        $route = new Route('GET', '/users/{id}/orders/{orderId}', ['Controller', 'orders']);

        $this->assertSame(1, preg_match($route->regex, '/users/42/orders/7', $matches));
        $this->assertSame('42', $matches[1]);
        $this->assertSame('7', $matches[2]);
    }

    public function testStoresTheHandlerSpecVerbatim(): void
    {
        $route = new Route('POST', '/users', ['UserController', 'create']);
        $this->assertSame(['UserController', 'create'], $route->handlerSpec);
    }
}
