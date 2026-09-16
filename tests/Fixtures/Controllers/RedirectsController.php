<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Post, JSONFormatter};
use Waypoint\Http\{Redirect, Response, View};

#[Controller('/redirects')]
class RedirectsController
{
    #[Get('/default')]
    public function default(): Redirect
    {
        return new Redirect('/landed');
    }

    #[Get('/permanent')]
    public function permanent(): Redirect
    {
        return new Redirect('https://example.com/moved', 301);
    }

    /** The View|Redirect union a real form handler ends up with -- see the Redirect class doc. */
    #[Post('/form')]
    public function form(Response $res, #[\Waypoint\Attributes\Query] ?string $fail = null): View|Redirect
    {
        if ($fail !== null) {
            return new View('HomePage', null, partial: true);
        }
        $res->withCookie('session', 'abc');
        return new Redirect('/landed', 303);
    }

    /** A Redirect ignores whatever formatter the route declares -- no body is ever written. */
    #[Get('/formatted')]
    #[JSONFormatter]
    public function formatted(): Redirect
    {
        return new Redirect('/landed');
    }
}
