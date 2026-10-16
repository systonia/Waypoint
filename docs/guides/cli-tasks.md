---
title: CLI tasks
description: "Run maintenance jobs from the same front controller with #[Manager] classes and #[Task] methods."
order: 130
---

The front controller that serves HTTP also runs command-line tasks: imports, cleanups, anything a cron job starts. A task is a method with `#[Task]` on a class with `#[Manager]`:

```php
<?php

namespace App\Tasks;

use App\Services\Newsletter;
use Waypoint\Attributes\{Inject, Manager, Task};
use Waypoint\Logger;

#[Manager('newsletter')]
final class NewsletterTasks
{
    #[Inject]
    private Newsletter $newsletter;

    #[Inject]
    private Logger $logger;

    /** @param list<string> $argv */
    #[Task('send')]
    public function send(array $argv, int $argc): string
    {
        $dryRun = in_array('--dry-run', $argv, true);
        $list = $this->newsletter->subscribe('digest@example.com');
        $this->logger->info('Newsletter sent', ['list' => $list, 'dryRun' => $dryRun]);
        return ($dryRun ? "Would send" : "Sent") . " the newsletter to list $list.\n";
    }
}
```

Attach it with the controllers:

```php
$app->attach([NewsletterController::class, NewsletterTasks::class]);
$app->serve();
```

and run it with PHP:

```bash
php public/index.php newsletter:send --dry-run
```

- The task's name is `<manager>:<task>`, here `newsletter:send`. `#[Manager]` without a name gives plain task names. The task name alone (`send`) works as long as only one task has it; an ambiguous or unknown name fails.
- The method is called with PHP's `$argv` and `$argc`: `$argv[0]` is the script, `$argv[1]` the task name, the rest are the task's own arguments.
- What it returns is printed: a string as it is, anything else as JSON. The exit code is `0`.
- An exception prints its message to stderr and exits with `1`.
- The manager gets its `#[Inject]` properties like a controller, except `Request` and `Response`. It shares the configured services, Options and loggers with the HTTP side; there is no request id.

`serve()` runs the task when PHP runs on the command line. Under Tenma it starts the server instead, so tasks run with `php`. [`$app->runCli()`](../reference/Waypoint/App.md#runcli) runs the task named in `$argv` and returns the exit code without exiting.

See also: [`Manager`](../reference/Waypoint/Attributes/Manager.md), [`Task`](../reference/Waypoint/Attributes/Task.md), [Deployment](deployment.md#scheduled-tasks).
