<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * The layout every View of a controller class, or of one route method, renders in: a file name under
 * RendererOptions::$directory, such as `_Backoffice.php`. `#[Layout(null)]` renders without one.
 * The method's wins over the class's, the class's over its parents'. A layout given to the View itself
 * (`new View(..., layout: ...)`) wins over both, and RendererOptions::$layout applies where none is set.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Layout
{
    public function __construct(public ?string $file)
    {
    }
}
