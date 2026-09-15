<?php

namespace Waypoint;

/**
 * Holds the current request's correlation id (Request::$id) for the
 * duration of one request. Container-resolved as a shared singleton --
 * App::handleHttp() sets it once, right after Request::capture(), and
 * Logger::log() reads it back (via Waypoint::getConfig(), the same
 * "resolve the live container instance" pattern Logger/Environment
 * already use for their own Options classes) to stamp every log line
 * automatically, instead of every Logger::info() call site having to pass
 * the id along by hand.
 *
 * Deliberately not itself #[Inject]-able into arbitrary services the way
 * Options classes are meant to be configured: nothing outside
 * App::handleHttp()/Logger should ever need to touch this directly.
 */
class RequestContext
{
    private ?string $requestId = null;

    /** Called once per request by App::handleHttp() -- null clears it again once the request finishes. */
    public function setRequestId(?string $id): void
    {
        $this->requestId = $id;
    }

    /** The current request's id, or null outside of a request (e.g. a CLI task, or before handleHttp() has run). */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
