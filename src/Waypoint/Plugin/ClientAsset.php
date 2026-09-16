<?php

namespace Waypoint\Plugin;

/** CSS/JS files a plugin ships; compiled into the asset pipeline (content-hashed) and rendered by View::pluginAssetTags(). */
interface ClientAsset
{
    /** @return array<string, string> {logical filename ending in .css or .js => absolute source path} */
    public function assets(): array;
}
