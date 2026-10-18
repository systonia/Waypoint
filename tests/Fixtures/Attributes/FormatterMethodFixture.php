<?php

namespace Waypoint\Tests\Fixtures\Attributes;

use Waypoint\Attributes\{FileFormatter, JSONFormatter, SimpleXmlFormatter};

class FormatterMethodFixture
{
    #[FileFormatter('text/plain', 'foo.txt')]
    public function file() {}

    #[JSONFormatter]
    public function json() {}

    #[SimpleXmlFormatter]
    public function xml() {}
}
