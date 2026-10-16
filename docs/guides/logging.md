---
title: Logging
description: Send log lines to PSR-3 loggers or Monolog, follow a request through the logs by its id, and keep secrets and personal data out of them.
order: 100
---

[`Waypoint\Logger`](../reference/Waypoint/Logger.md) is a PSR-3 logger that passes every line on to the loggers configured in [`LoggerOptions`](../reference/Waypoint/Options/LoggerOptions.md). Inject it wherever something is worth logging:

```php
<?php

namespace App\Services;

use Waypoint\Attributes\Inject;
use Waypoint\Logger;

final class Checkout
{
    #[Inject]
    private Logger $logger;

    public function pay(string $orderId, int $cents): void
    {
        $this->logger->info('Payment started', ['order' => $orderId, 'cents' => $cents]);
        // …
    }
}
```

## Where log lines go

As long as no logger is configured, lines of level `error` and worse go to PHP's `error_log`: stderr under Tenma and `php -S`, the server's error log under PHP-FPM. Waypoint's own errors therefore never vanish unseen. Everything below `error` is dropped.

[`add()`](../reference/Waypoint/Options/LoggerOptions.md#add) registers any `Psr\Log\LoggerInterface`, optionally for some levels only; [`addMono()`](../reference/Waypoint/Options/LoggerOptions.md#addmono) creates a Monolog logger for a handler (with `monolog/monolog` installed):

```php
use Monolog\Handler\StreamHandler;
use Psr\Log\LogLevel;
use Waypoint\Options\LoggerOptions;

$app->configure(function (LoggerOptions $logs) {
    $logs->addMono('app', new StreamHandler('php://stderr'), [
        LogLevel::INFO, LogLevel::NOTICE, LogLevel::WARNING,
        LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY,
    ]);
    $logs->add($alertingLogger, [LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY]);
});
```

A line goes to every logger whose levels include its level; without a level list, a logger gets all of them. Once one logger is added, the `error_log` fallback is off. `$logs->add(new \Psr\Log\NullLogger())` silences everything.

The level list is explicit on purpose: Waypoint logs a `warning` for every route without `#[Version]` when it compiles the routes, so an app that doesn't version its routes leaves `warning` out of a logger that would otherwise fill up with those lines.

## What Waypoint logs

- Every exception that ends in a `500`, at `error`, with the exception under `exception` in the context. The client gets no details (see [Errors](errors.md#unexpected-exceptions)); the log line has them.
- Notes at `warning`: routes without `#[Version]` and invalid `#[Sunset]` dates when the routes are compiled, DTO property types the OpenAPI generator can't resolve when the spec is built.

## Request ids

Every request has a correlation id, `$req->id`:

- the incoming `X-Request-Id` header, or `X-Correlation-Id`, when it has at most 128 characters of `A-Z a-z 0-9 . _ : -`; a proxy or client in front can so pass its id along,
- otherwise a fresh UUID v4.

The id goes back to the client as `X-Request-ID`, and every line logged while the request runs gets it as `request_id` in its context, without passing it around:

```php
$this->logger->warning('Card declined', ['order' => $orderId]);
// context: ['request_id' => 'c0a8f6e2-…', 'order' => 'A-1001']
```

A support request that quotes the `X-Request-ID` of a failed response finds the matching log lines. Under Tenma the id follows the request into the tasks it starts and into its WebSocket or stream handler. Outside a request (a CLI task) there is no id.

## Redacting sensitive values

Mark properties that must never appear in a log with `#[Sensitive]` (credentials, tokens) or `#[PII]` (personal data: names, email addresses, phone numbers). [`Waypoint::redact()`](../reference/Waypoint/Waypoint.md#redact) returns an object's public properties with those values replaced:

```php
<?php

namespace App\Dto;

use Waypoint\Attributes\{Email, NotBlank, PII, Sensitive};
use Waypoint\FromArray;

final class SignUp
{
    use FromArray;

    #[NotBlank]
    #[Email]
    #[PII]
    public string $email = '';

    #[NotBlank]
    #[Sensitive]
    public string $password = '';

    public string $plan = 'free';
}
```

```php
$this->logger->info('Sign-up', ['form' => Waypoint::redact($signUp)]);
// ['email' => '**redacted**', 'password' => '**redacted**', 'plan' => 'free']
```

`#[Sensitive('[secret]')]` sets another placeholder. The attributes do nothing by themselves: only values passed through `redact()` are replaced, so log DTOs that way rather than as they are.

See also: [`Logger`](../reference/Waypoint/Logger.md), [`LoggerOptions`](../reference/Waypoint/Options/LoggerOptions.md), [`RequestContext`](../reference/Waypoint/RequestContext.md), [`Sensitive`](../reference/Waypoint/Attributes/Sensitive.md), [`PII`](../reference/Waypoint/Attributes/PII.md).
