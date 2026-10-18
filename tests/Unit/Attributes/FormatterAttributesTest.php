<?php

namespace Waypoint\Tests\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Waypoint\Attributes\{FileFormatter, JSONFormatter, SimpleXmlFormatter};
use Waypoint\Tests\Fixtures\Attributes\{FormatterClassFixture, FormatterMethodFixture};

final class FormatterAttributesTest extends TestCase
{
    public function testFileFormatterDefaults(): void
    {
        $attr = new FileFormatter();
        $this->assertNull($attr->mimetype);
        $this->assertNull($attr->filename);
        $this->assertFalse($attr->download);
    }

    public function testFileFormatterOnClass(): void
    {
        $attr = (new ReflectionClass(FormatterClassFixture::class))
            ->getAttributes(FileFormatter::class)[0]->newInstance();

        $this->assertSame('application/pdf', $attr->mimetype);
        $this->assertSame('report.pdf', $attr->filename);
        $this->assertTrue($attr->download);
    }

    public function testFileFormatterOnMethodDefaultsDownloadToFalse(): void
    {
        $method = (new ReflectionClass(FormatterMethodFixture::class))->getMethod('file');
        $attr = $method->getAttributes(FileFormatter::class)[0]->newInstance();

        $this->assertSame('text/plain', $attr->mimetype);
        $this->assertSame('foo.txt', $attr->filename);
        $this->assertFalse($attr->download);
    }

    public function testJsonFormatterIsInstantiable(): void
    {
        $method = (new ReflectionClass(FormatterMethodFixture::class))->getMethod('json');
        $attr = $method->getAttributes(JSONFormatter::class)[0]->newInstance();

        $this->assertInstanceOf(JSONFormatter::class, $attr);
    }

    public function testSimpleXmlFormatterIsInstantiable(): void
    {
        $method = (new ReflectionClass(FormatterMethodFixture::class))->getMethod('xml');
        $attr = $method->getAttributes(SimpleXmlFormatter::class)[0]->newInstance();

        $this->assertInstanceOf(SimpleXmlFormatter::class, $attr);
    }
}
