---
title: CLI tasks
description: "Run maintenance jobs from the same front controller with #[Manager] classes and #[Task] methods."
order: 130
---

The front controller that serves HTTP also runs command-line tasks: migrations, imports, cleanups, anything a cron job starts. A task is a method with `#[Task]` on a class with `#[Manager]`. The Bakery keeps its schema and demo data this way (shortened from `src/Tasks/DatabaseTasks.php`):

```php
<?php

namespace Bakery\Tasks;

use Data\Postgres;
use Waypoint\Attributes\{Inject, Manager, Task};

/** The schema and the demo data: `tenma index.php db:migrate`, `db:seed`, `db:reset`. */
#[Manager('db')]
final class DatabaseTasks
{
    private const DIRECTORY = __DIR__ . '/../../database';

    #[Inject]
    private Postgres $db;

    /** Applies database/migrations/*.sql in name order, each file once, all in one transaction. */
    #[Task('migrate')]
    public function migrate(): string
    {
        $ran = $this->db->transaction(function (Postgres $tx): array {
            // … every file not yet in schema_migrations
        });
        return 'Applied: ' . ($ran === [] ? 'nothing new' : implode(', ', $ran)) . "\n";
    }

    /** Migrates, then loads database/seed.sql: for an empty database. */
    #[Task('seed')]
    public function seed(): string
    {
        $this->migrate();
        $this->db->script(new \FileSystem\FileInfo(self::DIRECTORY . '/seed.sql')->read());
        return "Seeded the demo data.\n";
    }

    /** Drops every table, then migrates and seeds. */
    #[Task('reset')]
    public function reset(): string
    {
        $this->db->script('DROP SCHEMA public CASCADE; CREATE SCHEMA public;');
        return $this->seed();
    }
}
```

Attach it with the controllers (from `app.php`):

```php
$app->attach([
    ShopController::class, SessionController::class, BackofficeController::class, ApiController::class,
    DatabaseTasks::class,
]);
$app->serve();
```

and run it with the front controller and the task's name:

```bash
tenma index.php db:reset
```

A task that takes arguments receives them as PHP's `$argv` and `$argc`. A task that sets the stock of one product, `tenma index.php stock:set croissant 40`:

```php
<?php

namespace Bakery\Tasks;

use Bakery\Repositories\Products;
use Waypoint\Attributes\{Inject, Manager, Task};

#[Manager('stock')]
final class StockTasks
{
    #[Inject]
    private Products $products;

    /** @param list<string> $argv */
    #[Task('set')]
    public function set(array $argv, int $argc): string
    {
        if ($argc !== 4) {
            throw new \InvalidArgumentException("Usage: stock:set <slug> <count>");
        }
        $product = $this->products->bySlug($argv[2]) ?? throw new \InvalidArgumentException("No product $argv[2]");
        $this->products->setStock($product->id, (int) $argv[3]);
        return "$product->slug: $argv[3] left.\n";
    }
}
```

- The task's name is `<manager>:<task>`, here `db:reset` and `stock:set`. `#[Manager]` without a name gives plain task names. The task name alone (`reset`) works as long as only one task has it; an ambiguous or unknown name fails.
- The method is called with PHP's `$argv` and `$argc`: `$argv[0]` is the script, `$argv[1]` the task name, the rest are the task's own arguments.
- What it returns is printed: a string as it is, anything else as JSON. The exit code is `0`.
- An exception prints its message to stderr and exits with `1`.
- The manager gets its `#[Inject]` properties like a controller, except `Request` and `Response`. It shares the configured services, Options and loggers with the HTTP side; there is no request id.

`serve()` runs the task when PHP runs on the command line. Under Tenma it does too when the script gets arguments: `tenma index.php db:reset` runs the task in a Tenma task of its own, so it can use what only Tenma offers (its Postgres pool, `Http\Client`), and exits with the task's code; without arguments it starts the server. The Bakery's database tasks run this way because Tenma's Postgres client needs its event loop. A task under Tenma runs as `production` unless `APP_ENV` says otherwise (see [APP_ENV](application.md#app_env)); `APP_ENV=development` in a local `.env` makes it `development`. [`$app->runCli()`](../reference/Waypoint/App.md#runcli) runs the task named in `$argv` and returns the exit code without exiting.

See also: [`Manager`](../reference/Waypoint/Attributes/Manager.md), [`Task`](../reference/Waypoint/Attributes/Task.md), [Deployment](deployment.md#scheduled-tasks).
