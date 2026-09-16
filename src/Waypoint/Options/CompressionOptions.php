<?php

namespace Waypoint\Options;

/** gzip response compression (Response::send()). Configure via App::configure(); #[NoGzip] opts a single route out. */
class CompressionOptions
{
    /** Master switch. */
    public bool $enabled = true;

    /** A body shorter than this (bytes) is sent uncompressed -- gzip's framing overhead makes it a net loss. */
    public int $minBytes = 1024;
}
