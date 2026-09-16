<?php

/**
 * Real front controller for the end-to-end suite. Run standalone by PHP's
 * built-in web server (php -S), not included by PHPUnit directly -- every
 * request re-executes this file from scratch, exactly like a real
 * deployment (no shared state between requests), which is the whole point
 * of testing against it instead of calling App::handleHttp() in-process.
 */

require __DIR__ . '/../../../vendor/autoload.php';

use Waypoint\Waypoint;
use Waypoint\Options\{JWTOptions, RendererOptions, FileSystemOptions, CorsOptions, OpenAPIOptions};
use Waypoint\Tests\Fixtures\Controllers\{
    CustomersController,
    OrdersController,
    ProductsController,
    GuardedController,
    WhoAmIController,
    MiddlewareController,
    ArgBindingController,
};
use Waypoint\Tests\Fixtures\Managers\MaintenanceManager;

$app = Waypoint::create();

$app->configure(function (FileSystemOptions $fs) {
    $fs->publicDirectory = __DIR__ . '/../../Fixtures/Public';
    $fs->cacheDirectory = sys_get_temp_dir() . '/e2e-cache';
});

$app->configure(function (JWTOptions $opts) {
    $opts->secret = 'e2e-test-secret';
});
$app->configure(function (RendererOptions $opts) {
    $opts->directory = __DIR__ . '/../../Fixtures/Views';
    $opts->layout = '_Layout.php';
});

$app->configure(function (OpenAPIOptions $opts) {
    $opts->enabled = true;
});

$app->configure(function (CorsOptions $opts) {
    $opts->allowOrigin = '*';
    $opts->allowMethods = 'GET, POST, OPTIONS';
});
$app->useCors();
$app->useJwt();

$app->attach([
    CustomersController::class,
    OrdersController::class,
    ProductsController::class,
    GuardedController::class,
    WhoAmIController::class,
    MiddlewareController::class,
    ArgBindingController::class,
    MaintenanceManager::class,
]);

$app->run();
