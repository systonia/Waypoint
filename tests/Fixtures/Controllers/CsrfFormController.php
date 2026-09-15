<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Http\View;
use Waypoint\Attributes\{Controller, Get, Post, SkipCsrf};

/**
 * Exercises Csrf end to end through a real dispatch(): /form renders a
 * View carrying the #[Inject]ed Csrf token (see CsrfView), /submit is the
 * state-changing route CSRF verification actually guards, and
 * /submit-unprotected is the same shape with #[SkipCsrf] to prove the
 * opt-out.
 */
#[Controller('/csrf')]
class CsrfFormController
{
    #[Get('/form')]
    public function form(): View
    {
        return new View('CsrfView', partial: true);
    }

    #[Post('/submit')]
    public function submit(): string
    {
        return 'submitted';
    }

    #[Post('/submit-unprotected')]
    #[SkipCsrf]
    public function submitUnprotected(): string
    {
        return 'submitted without csrf';
    }
}
