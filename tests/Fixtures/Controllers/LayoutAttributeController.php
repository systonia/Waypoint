<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Attributes\{Controller, Get, Layout};
use Waypoint\Http\View;

/** Every source of a layout once: the parent's #[Layout], the method's, #[Layout(null)] and the View's own. */
#[Controller('/layout-attribute')]
class LayoutAttributeController extends LayoutAttributeBase
{
    #[Get('/inherited')]
    public function inherited(): View
    {
        return new View('HomePage');
    }

    #[Get('/method')]
    #[Layout('_Layout.php')]
    public function method(): View
    {
        return new View('HomePage');
    }

    #[Get('/none')]
    #[Layout(null)]
    public function none(): View
    {
        return new View('HomePage');
    }

    #[Get('/explicit')]
    #[Layout(null)]
    public function explicit(): View
    {
        return new View('HomePage', layout: 'AltLayout.php');
    }
}
