<?php

namespace Waypoint\Tests\Fixtures\Plugins;

use Waypoint\Plugin\{PluginBase, ViewHelper};

/** Depends on EchoPlugin and offers the same view helper name, to test both boot-time checks. */
final class NeedsEchoPlugin extends PluginBase implements ViewHelper
{
    public function __construct(private bool $clashingHelper = false)
    {
    }

    public function name(): string
    {
        return 'needs-echo';
    }

    public function requires(): array
    {
        return [EchoPlugin::class];
    }

    public function hooks(): array
    {
        return [$this];
    }

    public function helpers(): array
    {
        return $this->clashingHelper ? ['shout' => fn(): string => ''] : ['whisper' => fn(string $t): string => strtolower($t)];
    }
}
