<?php

namespace Waypoint\Tests\Fixtures\Support;

use Waypoint\Attributes\{Sensitive, PII};

/** Plain object (not FromArray) exercising Waypoint::redact() -- see WaypointTest. */
class RedactableDTO
{
    public string $username = '';

    #[Sensitive]
    public string $password = '';

    #[Sensitive(placeholder: '***')]
    public string $apiKey = '';

    #[PII]
    public string $email = '';

    /** Never assigned -- exercises redact()'s isInitialized() guard. */
    public string $uninitialized;

    private string $internal = 'never exposed';
}
