<?php

namespace Waypoint;

/**
 * The current request's correlation id (Request::$id), held as a container
 * singleton for the duration of one request so Logger can stamp it onto
 * every line without call sites passing it around. Set/cleared by
 * App::handleHttp().
 */
class RequestContext
{
    private ?string $requestId = null;

    public function setRequestId(?string $id): void
    {
        $this->requestId = $id;
    }

    /** Null outside of a request (CLI task, or before handleHttp() ran). */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
