<?php

namespace Waypoint\Tests\Fixtures\Controllers;

use Waypoint\Http\View;
use Waypoint\Attributes\{Controller, Get};

/**
 * Dedicated fixture for proving a layout template (LayoutWithAssets.php,
 * see Tests/Fixtures/Views/) gets its own sibling CSS/JS discovered,
 * compiled and served through the same ViewAssets pipeline as a plain
 * view -- View::layoutAssetTags()/layoutScopeAttribute() are what expose
 * it to the template itself.
 */
#[Controller('/layout-assets')]
class LayoutAssetsController
{
    #[Get]
    public function show(): View
    {
        return new View('HomePage', layout: 'LayoutWithAssets.php');
    }
}
