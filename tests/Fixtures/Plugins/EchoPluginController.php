<?php

namespace Waypoint\Tests\Fixtures\Plugins;

use Waypoint\Attributes\{Controller, Get};
use Waypoint\Http\{Request, View};

#[Controller('/echo')]
class EchoPluginController
{
    #[Get('/plain')]
    public function plain(): array
    {
        return ['ok' => true];
    }

    #[Get('/tagged')]
    #[Tagged('blue')]
    public function tagged(): array
    {
        return ['ok' => true];
    }

    #[Get('/forbidden')]
    #[Tagged('forbidden')]
    public function forbidden(): array
    {
        return ['ok' => true];
    }

    #[Get('/shout')]
    public function shout(#[Shout] string $word): array
    {
        return ['word' => $word];
    }

    #[Get('/result')]
    public function result(): EchoResult
    {
        return new EchoResult('rendered by plugin');
    }

    #[Get('/view')]
    public function view(Request $req): View
    {
        return new View('PluginView', partial: $req->acceptPartial);
    }
}
