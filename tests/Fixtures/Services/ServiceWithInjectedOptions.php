<?php

namespace Waypoint\Tests\Fixtures\Services;

use Waypoint\Attributes\Inject;
use Waypoint\Options\FileSystemOptions;

/**
 * A container-managed service that #[Inject]s an Options class -- proves
 * App::initDependencyInjection() reuses an Options instance the app already
 * configure()d (before attach()) instead of clobbering it with a fresh,
 * unconfigured one just because discoverAllClasses() also found it here.
 */
class ServiceWithInjectedOptions
{
    #[Inject]
    private FileSystemOptions $fileSystemOptions;

    public function getFileSystemOptions(): ?FileSystemOptions
    {
        return isset($this->fileSystemOptions) ? $this->fileSystemOptions : null;
    }
}
