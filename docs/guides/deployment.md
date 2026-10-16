---
title: Deployment
description: Put a Waypoint app into production under Tenma or PHP-FPM, with configuration, the route cache, OPcache, scheduled tasks and a release checklist.
order: 180
---

A Waypoint app is a Composer project with a front controller. Deploying it means installing the dependencies without development packages, providing the configuration, and running it under Tenma or PHP-FPM.

```bash
composer install --no-dev --classmap-authoritative
```

## Configuration

Production values come from the [environment](application.md#environment-and-env-files): real environment variables set by the service manager or container, or a `.env` file next to the code that is not in the repository. `config.json` and `config.production.json` hold committed defaults; `.env` overrides them, and a real environment variable overrides both.

```bash
# .env on the server, readable only by the app's user
JWT_SECRET=…
CSRF_SECRET=…
DATABASE_URL=postgres://shop@localhost/shop
```

`APP_ENV` is `production` by default under PHP-FPM and under Tenma; only `tenma --watch`, `php -S` and the command line default to `development`. Setting `APP_ENV=production` explicitly does no harm and keeps a server from depending on how it was started.

## Under Tenma

One process (with one or more workers) serves every request; see [Running under Tenma](tenma.md). A systemd unit:

```ini
[Unit]
Description=Shop
After=network-online.target postgresql.service
Wants=network-online.target

[Service]
User=shop
WorkingDirectory=/srv/shop/current
Environment=APP_ENV=production
Environment=PORT=8080
ExecStart=/usr/bin/tenma --workers auto public/index.php
Restart=always
KillSignal=SIGTERM
TimeoutStopSec=15

[Install]
WantedBy=multi-user.target
```

Or a container image built on Tenma's:

```dockerfile
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --classmap-authoritative --no-scripts

FROM ghcr.io/systonia/tenma
WORKDIR /app
COPY --from=vendor /app/vendor vendor
COPY . .
ENV APP_ENV=production
CMD ["--workers", "auto", "/app/public/index.php"]
```

Put a reverse proxy with TLS in front and list it in [`ProxyOptions`](proxies.md). Tenma serves the public directory itself. Each worker compiles the routes once at start, so the [cache](caching.md) only shortens the start; restarting the service deploys new code. Errors go to stderr, which the service manager collects, unless a [logger](logging.md) sends them elsewhere.

## PHP-FPM

PHP runs the front controller for every request; see [Runtimes](runtimes.md#php-fpm-and-php--s). The web server serves files from `public/` directly and passes everything else to `public/index.php`. With nginx:

```nginx
server {
    listen 443 ssl;
    server_name shop.example.com;
    root /srv/shop/current/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param HTTPS on;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }
}
```

Since the app is built anew for every request, two settings matter most:

- **OPcache** on (`opcache.enable=1`), so PHP doesn't compile the code on every request.
- **A route cache in trust mode**, so Waypoint doesn't reflect the controllers on every request:

```php
$app->configure(function (FileSystemOptions $fs, Environment $env) {
    $fs->cacheDirectory = dirname(__DIR__) . '/var/cache';
    $fs->cacheValidate = $env->isDev();
});
```

In trust mode, empty the cache directory as part of every release (see [Caching](caching.md#trust-mode)). With validate mode instead, changes are picked up on their own, at the cost of a reflection pass per request.

The cache directory must be writable by the PHP-FPM user and by no one else.

## Scheduled tasks

[CLI tasks](cli-tasks.md) run from the same front controller with `php`, for example from cron:

```text
*/15 * * * * cd /srv/shop/current && APP_ENV=production php public/index.php newsletter:send >> /var/log/shop/tasks.log 2>&1
```

A task exits with `1` and its message on stderr when it fails, which cron and systemd timers report.

## Releasing

1. Install the new release next to the old one (`/srv/shop/releases/<version>`), with `composer install --no-dev --classmap-authoritative`.
2. Provide `.env` or the environment variables.
3. In trust mode, start with an empty cache directory.
4. Switch the `current` symlink.
5. Restart Tenma, or reload PHP-FPM so OPcache drops the old code.

Go through the [security checklist](security.md#checklist-before-going-live) before the first release.

See also: [Caching](caching.md), [Proxies](proxies.md), [Running under Tenma](tenma.md), [Runtimes](runtimes.md).
