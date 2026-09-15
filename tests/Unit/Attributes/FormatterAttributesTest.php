<?php

namespace Waypoint\Tests\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Waypoint\Attributes\{FileFormatter, JSONFormatter, SimpleXmlFormatter};

#[FileFormatter('application/pdf', 'report.pdf', true)]
class FormatterAttributes_ClassFixture
{
}

class FormatterAttributes_MethodFixture
{
    #[FileFormatter('text/plain', 'foo.txt')]
    public function file() {}

    #[JSONFormatter]
    public function json() {}

    #[SimpleXmlFormatter]
    public function xml() {}
}

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
        $attr = (new ReflectionClass(FormatterAttributes_ClassFixture::class))
            ->getAttributes(FileFormatter::class)[0]->newInstance();

        $this->assertSame('application/pdf', $attr->mimetype);
        $this->assertSame('report.pdf', $attr->filename);
        $this->assertTrue($attr->download);
    }

    public function testFileFormatterOnMethodDefaultsDownloadToFalse(): void
    {
        $method = (new ReflectionClass(FormatterAttributes_MethodFixture::class))->getMethod('file');
        $attr = $method->getAttributes(FileFormatter::class)[0]->newInstance();

        $this->assertSame('text/plain', $attr->mimetype);
        $this->assertSame('foo.txt', $attr->filename);
        $this->assertFalse($attr->download);
    }

    public function testJsonFormatterIsInstantiable(): void
    {
        $method = (new ReflectionClass(FormatterAttributes_MethodFixture::class))->getMethod('json');
        $attr = $method->getAttributes(JSONFormatter::class)[0]->newInstance();

        $this->assertInstanceOf(JSONFormatter::class, $attr);
    }

    public function testSimpleXmlFormatterIsInstantiable(): void
    {
        $method = (new ReflectionClass(FormatterAttributes_MethodFixture::class))->getMethod('xml');
        $attr = $method->getAttributes(SimpleXmlFormatter::class)[0]->newInstance();

        $this->assertInstanceOf(SimpleXmlFormatter::class, $attr);
    }
}
