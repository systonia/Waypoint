<?php

namespace Waypoint\Tests\Fixtures\Attributes;

use Waypoint\Attributes\FileFormatter;

#[FileFormatter('application/pdf', 'report.pdf', true)]
class FormatterClassFixture
{
}
