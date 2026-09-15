<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Tests\Fixtures\Services\{AnotherService, ServiceWithInjectedDependency};

#[Controller('/nested-injection')]
class NestedInjectionController
{
    #[Inject]
    private ServiceWithInjectedDependency $service;

    #[Get]
    public function check(): array
    {
        return [
            'serviceResolved' => isset($this->service),
            'nestedDependencyResolved' => $this->service->getOther() instanceof AnotherService,
        ];
    }
}
