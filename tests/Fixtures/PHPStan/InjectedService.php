<?php

namespace Waypoint\Tests\Fixtures\PHPStan;

use Waypoint\Attributes\Inject;
use Waypoint\Environment;

/** Analysed by InjectedPropertiesTest: the property is only read here, the container writes it */
final class InjectedService
{
    #[Inject]
    private Environment $env;

    public function port(): mixed
    {
        return $this->env->get('PORT');
    }
}
