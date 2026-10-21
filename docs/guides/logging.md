---
title: Logging
description: Send log lines to PSR-3 loggers or Monolog, follow a request through the logs by its id, and keep secrets and personal data out of them.
order: 100
---

[`Waypoint\Logger`](../reference/Waypoint/Logger.md) is a PSR-3 logger that passes every line on to the loggers configured in [`LoggerOptions`](../reference/Waypoint/Options/LoggerOptions.md). Inject it wherever something is worth logging:

```php
<?php

namespace Bakery\Services;

use JsonException;
use RuntimeException;
use Waypoint\Attributes\Inject;
use Waypoint\Logger;

/** The week's forecast at the bakery, from api.open-meteo.com. */
final class Weather
{
    #[Inject]
    private Logger $logger;

    /** @return array<string, WeatherDay>|null */
    public function week(): ?array
    {
        $url = 'https://api.open-meteo.com/v1/forecast?latitude=47.99&longitude=7.85&daily=weather_code';
        try {
            $response = new \Http\Client(2.5)->get($url);
            // …
        } catch (RuntimeException|JsonException $e) {
            $this->logger->warning('weather: ' . $e->getMessage());
            return null;
        }
    }
}
```

This is shortened from the Bakery's `src/Services/Weather.php`: when the weather service is down, the product page goes on without the forecast, and the log says why.

## Where log lines go

As long as no logger is configured, lines of level `error` and worse go to PHP's `error_log`: stderr under Tenma and `php -S`, the server's error log under PHP-FPM. Waypoint's own errors therefore never vanish unseen. Everything below `error` is dropped.

[`add()`](../reference/Waypoint/Options/LoggerOptions.md#add) registers any `Psr\Log\LoggerInterface`, optionally for some levels only; [`addMono()`](../reference/Waypoint/Options/LoggerOptions.md#addmono) creates a Monolog logger for a handler (with `monolog/monolog` installed):

```php
use Monolog\Handler\StreamHandler;
use Psr\Log\LogLevel;
use Waypoint\Options\LoggerOptions;

$app->configure(function (LoggerOptions $logs) {
    $logs->addMono('bakery', new StreamHandler('php://stderr'), [
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
- otherwise a fresh UUID v4 from [`Uuid::v4()`](../reference/Waypoint/Support/Uuid.md), which apps can call for their own ids as well.

The id goes back to the client as `X-Request-ID`, and every line logged while the request runs gets it as `request_id` in its context, without passing it around:

```php
$this->logger->warning('Out of stock', ['order' => $order->code, 'product' => 'croissant']);
// context: ['request_id' => 'c0a8f6e2-…', 'order' => '3f9a1c2b7d', 'product' => 'croissant']
```

A support request that quotes the `X-Request-ID` of a failed response finds the matching log lines. Under Tenma the id follows the request into the tasks it starts and into its WebSocket or stream handler. Outside a request (a CLI task) there is no id.

## Redacting sensitive values

Mark properties that must never appear in a log with `#[Sensitive]` (credentials, tokens) or `#[PII]` (personal data: names, email addresses, phone numbers). [`Waypoint::redact()`](../reference/Waypoint/Waypoint.md#redact) returns an object's public properties with those values replaced:

```php
<?php

namespace Bakery\Requests;

use Waypoint\Attributes\{Email, NotBlank, PII, Regex, Sensitive};
use Waypoint\FromArray;

final class CheckoutForm
{
    use FromArray;

    #[NotBlank]
    #[PII]
    public string $name = '';

    #[NotBlank]
    #[Email]
    #[PII]
    public string $email = '';

    #[NotBlank]
    #[Regex('/^\d{4}-\d{2}-\d{2}$/')]
    public string $pickupDate = '';
}
```

```php
$this->logger->info('Checkout', ['form' => Waypoint::redact($form)]);
// ['name' => '**redacted**', 'email' => '**redacted**', 'pickupDate' => '2026-10-20']
```

The password of the back office's `LoginForm` would take `#[Sensitive]` the same way.

`#[Sensitive('[secret]')]` sets another placeholder. The attributes do nothing by themselves: only values passed through `redact()` are replaced, so log DTOs that way rather than as they are.

See also: [`Logger`](../reference/Waypoint/Logger.md), [`LoggerOptions`](../reference/Waypoint/Options/LoggerOptions.md), [`RequestContext`](../reference/Waypoint/RequestContext.md), [`Sensitive`](../reference/Waypoint/Attributes/Sensitive.md), [`PII`](../reference/Waypoint/Attributes/PII.md).
