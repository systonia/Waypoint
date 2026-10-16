<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Controllers\ArgBindingController;
use Waypoint\Tests\Fixtures\Controllers\DocumentedController;

final class ArgumentBindingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Waypoint::create()->attach([ArgBindingController::class, DocumentedController::class]);
    }

    public function testRequestResponseAndRouterCanBeInjectedAsControllerProperties(): void
    {
        $output = $this->dispatch('GET', '/argbinding/props');

        $this->assertSame(
            ['hasRequest' => true, 'hasResponse' => true, 'hasRouter' => true],
            json_decode($output, true)
        );
    }

    public function testResponseCanBeBoundAsAPlainTypedMethodParameter(): void
    {
        $output = $this->dispatch('GET', '/argbinding/response-param');

        $this->assertSame(['ok' => true], json_decode($output, true));
        $headers = $this->sentHeaderLines();
        $this->assertTrue(
            (bool) array_filter($headers, fn($h) => str_starts_with(strtolower($h), 'x-from-param:'))
        );
    }

    public function testUnattributedScalarParameterBindsByNameFromTheRoutePlaceholder(): void
    {
        $output = $this->dispatch('GET', '/argbinding/scalar/42');

        $this->assertSame(['id' => 42, 'type' => 'integer'], json_decode($output, true));
    }

    public function testScalarQueryValuesConvertByTheirMeaningNotByPhpCasting(): void
    {
        $output = $this->dispatch('GET', '/argbinding/typed?active=false&page=3&ratio=0.5');
        $this->assertSame(['active' => false, 'page' => 3, 'ratio' => 0.5], json_decode($output, true));

        $output = $this->dispatch('GET', '/argbinding/typed?active=yes&page=-2&ratio=2.5');
        $this->assertSame(['active' => true, 'page' => -2, 'ratio' => 2.5], json_decode($output, true));
    }

    /** @return array<string, array{string, string}> */
    public static function invalidScalarQueries(): array
    {
        return [
            'int from text' => ['active=1&page=abc&ratio=1', 'page'],
            'int with trailing text' => ['active=1&page=12abc&ratio=1', 'page'],
            'int from a fraction' => ['active=1&page=1.5&ratio=1', 'page'],
            'float from text' => ['active=1&page=1&ratio=half', 'ratio'],
            'bool from text' => ['active=maybe&page=1&ratio=1', 'active'],
            'int from a list' => ['active=1&page[]=1&ratio=1', 'page'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidScalarQueries')]
    public function testAnUnconvertibleScalarQueryValueIsAValidationError(string $query, string $field): void
    {
        $output = $this->dispatch('GET', "/argbinding/typed?{$query}");

        $this->assertSame(422, $this->lastResponse?->status());
        $decoded = json_decode($output, true);
        $this->assertSame('Validation failed', $decoded['title']);
        $this->assertArrayHasKey($field, $decoded['errors']);
    }

    public function testABackedEnumBindsFromTheQueryAndThePath(): void
    {
        $this->assertSame(['status' => 'done', 'priority' => 2], json_decode($this->dispatch('GET', '/argbinding/enum?status=done&priority=2'), true));
        $this->assertSame(['status' => 'open', 'priority' => 1], json_decode($this->dispatch('GET', '/argbinding/enum?status=open'), true));
        $this->assertSame(['status' => 'open'], json_decode($this->dispatch('GET', '/argbinding/enum/open'), true));
    }

    public function testAValueNoEnumCaseHasIsAValidationErrorNamingTheValues(): void
    {
        $output = json_decode($this->dispatch('GET', '/argbinding/enum?status=gone'), true);
        $this->assertSame(422, $this->lastResponse?->status());
        $this->assertSame(['status' => 'This value should be one of: open, done.'], $output['errors']);

        $output = json_decode($this->dispatch('GET', '/argbinding/enum?status=open&priority=7'), true);
        $this->assertSame(['priority' => 'This value should be one of: 1, 2.'], $output['errors']);

        $output = json_decode($this->dispatch('GET', '/argbinding/enum'), true);
        $this->assertSame(['status' => 'This value is required.'], $output['errors']);
    }

    public function testANonNullableParameterNothingBindsFailsTheBoot(): void
    {
        Waypoint::reset();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot bind $when of ' . ArgumentBindingTest_Unbindable::class . '::show()');
        Waypoint::create()->attach([ArgumentBindingTest_Unbindable::class]);
    }

    public function testRouteParametersArriveUrlDecoded(): void
    {
        $output = $this->dispatch('GET', '/argbinding/name/J%C3%BCrgen%20M%C3%BCller');
        $this->assertSame(['name' => 'Jürgen Müller'], json_decode($output, true));

        // An encoded slash stays inside its segment
        $output = $this->dispatch('GET', '/argbinding/name/a%2Fb');
        $this->assertSame(['name' => 'a/b'], json_decode($output, true));

        // Decoded once, never twice
        foreach (['%2541' => '%41', '100%2525' => '100%25', '%252e%252e' => '%2e%2e', 'a%252Fb' => 'a%2Fb'] as $sent => $bound) {
            $output = $this->dispatch('GET', "/argbinding/name/$sent");
            $this->assertSame(['name' => $bound], json_decode($output, true), $sent);
        }
    }

    public function testTheLiteralPartsOfARoutePathMatchLiterally(): void
    {
        $output = $this->dispatch('GET', '/argbinding/file/report.json');
        $this->assertSame(['file' => 'report'], json_decode($output, true));

        // '.' is a dot, not any character
        $this->dispatch('GET', '/argbinding/file/reportxjson');
        $this->assertSame(404, $this->lastResponse?->status());

        // '+' is a plus, not a repetition
        $output = $this->dispatch('GET', '/argbinding/tag/c++/php');
        $this->assertSame(['tag' => 'php'], json_decode($output, true));
        $this->dispatch('GET', '/argbinding/tag/ccc/php');
        $this->assertSame(404, $this->lastResponse?->status());
    }

    public function testUnrecognizedClassParameterAlwaysBindsToNull(): void
    {
        $output = $this->dispatch('GET', '/argbinding/unknown');

        $this->assertSame(['thing' => null], json_decode($output, true));
    }

    public function testArrayBodyWithOfHydratesAndValidatesEachElementIntoTheGivenDto(): void
    {
        $output = $this->dispatch('POST', '/documented/bulk-products', [], [
            ['name' => 'Widget', 'sku' => 'AB12'],
            ['name' => 'Gadget', 'sku' => 'CD34'],
        ]);

        $this->assertSame(['count' => 2, 'skus' => ['AB12', 'CD34']], json_decode($output, true));
    }

    public function testArrayBodyWithOfRejectsAnInvalidElementWithItsIndexInTheErrors(): void
    {
        $output = $this->dispatch('POST', '/documented/bulk-products', [], [
            ['name' => 'Widget', 'sku' => 'AB12'],
            ['name' => '', 'sku' => 'CD34'], // fails #[NotBlank] on name
        ]);

        $decoded = json_decode($output, true);
        $this->assertSame('Validation failed', $decoded['title']);
        $this->assertArrayHasKey('1', $decoded['errors']);
        $this->assertArrayNotHasKey('0', $decoded['errors']);
    }

    /** @return array<string, array{mixed, string}> */
    public static function malformedCollections(): array
    {
        return [
            'scalar items' => [[1, 'two'], '0'],
            'a scalar between objects' => [[['name' => 'Widget', 'sku' => 'AB12'], 'oops'], '1'],
            'an object instead of a list' => [['name' => 'Widget', 'sku' => 'AB12'], 'body'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedCollections')]
    public function testAMalformedBodyCollectionIsAValidationErrorNotACrash(mixed $body, string $errorKey): void
    {
        $output = $this->dispatch('POST', '/documented/bulk-products', [], $body);

        $this->assertSame(422, $this->lastResponse?->status());
        $decoded = json_decode($output, true);
        $this->assertSame('Validation failed', $decoded['title']);
        $this->assertArrayHasKey($errorKey, $decoded['errors']);
    }
}

#[\Waypoint\Attributes\Controller('/unbindable')]
final class ArgumentBindingTest_Unbindable
{
    #[\Waypoint\Attributes\Get('')]
    public function show(\DateTimeImmutable $when): array
    {
        return [];
    }
}
