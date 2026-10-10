<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Http\Request;

/** Needs a constructor argument, so an app registers an instance of it in the container. */
#[Controller('/greeting')]
class GreetingController
{
    #[Inject]
    public Request $request;

    public function __construct(private string $greeting)
    {
    }

    #[Get]
    public function index(): string
    {
        return $this->greeting . ' ' . $this->request->query('name');
    }
}
