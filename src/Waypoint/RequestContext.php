<?php

namespace Waypoint;

use Waypoint\Support\FiberLocal;

/**
 * The current request's correlation id (Request::$id), held as a container
 * singleton for the duration of one request so Logger can stamp it onto
 * every line without call sites passing it around. Set/cleared by
 * App::handle(); per Fiber, so requests running interleaved in Fibers keep their own.
 */
class RequestContext
{
    /** @var FiberLocal<string> */
    private FiberLocal $requestId;

    public function __construct()
    {
        $this->requestId = new FiberLocal();
    }

    public function setRequestId(?string $id): void
    {
        $this->requestId->set($id);
    }

    /** Null outside of a request (CLI task, or before handle() ran). */
    public function getRequestId(): ?string
    {
        return $this->requestId->get();
    }
}
