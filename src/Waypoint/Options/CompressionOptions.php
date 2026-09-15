<?php

namespace Waypoint\Options;

/**
 * Configures gzip response compression (see Response::send()). Resolved
 * through the container like any other Options class -- configure it via
 * App::configure():
 *
 *   $app->configure(function (CompressionOptions $opts) {
 *       $opts->minBytes = 2048;
 *   });
 *
 * Individual controllers/routes can opt out entirely regardless of these
 * settings via #[NoGzip] -- see Waypoint\Attributes\NoGzip.
 */
class CompressionOptions
{
    /**
     * Master on/off switch for gzip compression. #[NoGzip] only ever turns
     * compression off for the route/controller it's on; this is the one
     * knob that turns it off everywhere.
     *
     * @var bool
     */
    public bool $enabled = true;

    /**
     * A response body shorter than this (in bytes, before compression) is
     * always sent uncompressed -- gzip's own framing overhead makes
     * compressing a small body a net loss over the wire, and it's pure
     * wasted CPU for no benefit.
     *
     * @var int
     */
    public int $minBytes = 1024;
}
