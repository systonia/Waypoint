<?php

namespace Waypoint\Tests\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Waypoint\Attributes\{Summary, Tags, Throws, Ignore, Schema, Property, Inject, Middleware, NoGzip, Sensitive, PII, Authenticated, Role, Permissions};
use Waypoint\Exceptions\NotFoundException;

#[Ignore]
#[Schema('Widget')]
#[NoGzip]
#[Authenticated]
#[Role('admin')]
#[Permissions(['users.manage', 'users.delete'])]
class MetadataAttributes_ClassFixture
{
    #[Property]
    public string $name = '';

    #[Inject]
    public $dependency;

    #[Sensitive]
    public string $password = '';

    #[Sensitive(placeholder: '***')]
    public string $customPlaceholder = '';

    #[PII]
    public string $email = '';
}

class MetadataAttributes_MethodFixture
{
    #[Summary('Does the thing')]
    #[Tags(['widgets', 'admin'])]
    #[Throws(exception: NotFoundException::class, status: 404, description: 'Widget missing')]
    #[Middleware([self::class, 'auth'])]
    #[Middleware([self::class, 'log'])]
    public function handle() {}

    public function auth() {}
    public function log() {}
}

/**
 * These attributes only carry OpenAPI/DI metadata that other parts of the
 * framework (Router, OpenAPIGenerator) read at compile time; here we just
 * confirm the shape they expose.
 */
final class MetadataAttributesTest extends TestCase
{
    public function testIgnoreIsInstantiable(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getAttributes(Ignore::class)[0]->newInstance();

        $this->assertInstanceOf(Ignore::class, $attr);
    }

    public function testNoGzipIsInstantiable(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getAttributes(NoGzip::class)[0]->newInstance();

        $this->assertInstanceOf(NoGzip::class, $attr);
    }

    public function testSchemaStoresName(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getAttributes(Schema::class)[0]->newInstance();

        $this->assertSame('Widget', $attr->name);
    }

    public function testPropertyIsInstantiable(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getProperty('name')->getAttributes(Property::class)[0]->newInstance();

        $this->assertInstanceOf(Property::class, $attr);
    }

    public function testInjectIsInstantiable(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getProperty('dependency')->getAttributes(Inject::class)[0]->newInstance();

        $this->assertInstanceOf(Inject::class, $attr);
    }

    public function testSensitiveDefaultsToTheRedactedPlaceholder(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getProperty('password')->getAttributes(Sensitive::class)[0]->newInstance();

        $this->assertSame('**redacted**', $attr->placeholder);
    }

    public function testSensitivePlaceholderIsOverridable(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getProperty('customPlaceholder')->getAttributes(Sensitive::class)[0]->newInstance();

        $this->assertSame('***', $attr->placeholder);
    }

    public function testPiiDefaultsToTheRedactedPlaceholder(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getProperty('email')->getAttributes(PII::class)[0]->newInstance();

        $this->assertSame('**redacted**', $attr->placeholder);
    }

    public function testAuthenticatedIsInstantiable(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getAttributes(Authenticated::class)[0]->newInstance();

        $this->assertInstanceOf(Authenticated::class, $attr);
    }

    public function testRoleStoresItsValue(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getAttributes(Role::class)[0]->newInstance();

        $this->assertSame('admin', $attr->role);
    }

    public function testPermissionsStoresItsList(): void
    {
        $attr = (new ReflectionClass(MetadataAttributes_ClassFixture::class))
            ->getAttributes(Permissions::class)[0]->newInstance();

        $this->assertSame(['users.manage', 'users.delete'], $attr->permissions);
    }

    public function testSummaryStoresText(): void
    {
        $method = (new ReflectionClass(MetadataAttributes_MethodFixture::class))->getMethod('handle');
        $attr = $method->getAttributes(Summary::class)[0]->newInstance();

        $this->assertSame('Does the thing', $attr->text);
    }

    public function testTagsStoresList(): void
    {
        $method = (new ReflectionClass(MetadataAttributes_MethodFixture::class))->getMethod('handle');
        $attr = $method->getAttributes(Tags::class)[0]->newInstance();

        $this->assertSame(['widgets', 'admin'], $attr->tags);
    }

    public function testThrowsStoresExceptionStatusAndDescription(): void
    {
        $method = (new ReflectionClass(MetadataAttributes_MethodFixture::class))->getMethod('handle');
        $attr = $method->getAttributes(Throws::class)[0]->newInstance();

        $this->assertSame(NotFoundException::class, $attr->exception);
        $this->assertSame(404, $attr->status);
        $this->assertSame('Widget missing', $attr->description);
    }

    public function testMiddlewareIsRepeatableAndStoresCallable(): void
    {
        $method = (new ReflectionClass(MetadataAttributes_MethodFixture::class))->getMethod('handle');
        $attrs = $method->getAttributes(Middleware::class);

        $this->assertCount(2, $attrs);
        $this->assertSame(
            [MetadataAttributes_MethodFixture::class, 'auth'],
            $attrs[0]->newInstance()->callable
        );
        $this->assertSame(
            [MetadataAttributes_MethodFixture::class, 'log'],
            $attrs[1]->newInstance()->callable
        );
    }
}
