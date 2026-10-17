<?php

namespace Waypoint\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use Waypoint\Enums\RouteType;
use Waypoint\Support\Row;

final class RowTest extends TestCase
{
    private static function row(): Row
    {
        return new Row([
            'id' => 7,
            'code' => 'a1',
            'max' => 21.5,
            'whole' => 3,
            'exact' => '123456789012345678.90',
            'paid' => true,
            'method' => 'static',
            'none' => null,
            'address' => ['city' => 'Bonn'],
            'lines' => [['product' => 1], ['product' => 2]],
            'tags' => ['x', 'y'],
            'name' => ['en' => 'Bread', 'de' => 'Brot'],
        ]);
    }

    public function testGettersReadTheirType(): void
    {
        $row = self::row();
        $this->assertSame(7, $row->int('id'));
        $this->assertSame('a1', $row->string('code'));
        $this->assertSame(21.5, $row->float('max'));
        $this->assertSame(3.0, $row->float('whole'));
        $this->assertSame(123456789012345678.90, $row->float('exact'));
        $this->assertTrue($row->bool('paid'));
        $this->assertSame(RouteType::Static, $row->enum('method', RouteType::class));
        $this->assertSame('Bonn', $row->row('address')->string('city'));
        $this->assertSame([1, 2], array_map(fn (Row $line) => $line->int('product'), $row->rows('lines')));
        $this->assertSame(['x', 'y'], $row->list('tags', 'string'));
        $this->assertSame(['en' => 'Bread', 'de' => 'Brot'], $row->map('name', 'string'));
        $this->assertTrue($row->has('none'));
        $this->assertFalse($row->has('missing'));
    }

    public function testNullableGettersTakeNullAndMissingKeys(): void
    {
        $row = self::row();
        foreach (['none', 'missing'] as $key) {
            $this->assertNull($row->nullableInt($key));
            $this->assertNull($row->nullableString($key));
            $this->assertNull($row->nullableFloat($key));
            $this->assertNull($row->nullableBool($key));
            $this->assertNull($row->nullableEnum($key, RouteType::class));
            $this->assertNull($row->nullableRow($key));
        }
        $this->assertSame(7, $row->nullableInt('id'));
    }

    /** @return iterable<string, array{\Closure(Row): mixed, string}> */
    public static function broken(): iterable
    {
        yield 'missing key' => [fn (Row $row) => $row->int('missing'), "Row has no value 'missing'."];
        yield 'null for int' => [fn (Row $row) => $row->int('none'), "Row value 'none' is not an int, null found."];
        yield 'string for int' => [fn (Row $row) => $row->int('code'), "Row value 'code' is not an int, string found."];
        yield 'int for string' => [fn (Row $row) => $row->string('id'), "Row value 'id' is not a string, int found."];
        yield 'text for float' => [fn (Row $row) => $row->float('code'), "Row value 'code' is not a number, string found."];
        yield 'int for bool' => [fn (Row $row) => $row->bool('id'), "Row value 'id' is not a bool, int found."];
        yield 'unknown case' => [fn (Row $row) => $row->enum('code', RouteType::class), "Row value 'code' is not a case of " . RouteType::class . ', string found.'];
        yield 'scalar for row' => [fn (Row $row) => $row->row('code'), "Row value 'code' is not an array, string found."];
        yield 'object for rows' => [fn (Row $row) => $row->rows('address'), "Row value 'address' is not a list, array found."];
        yield 'scalar in rows' => [fn (Row $row) => $row->rows('tags'), "Row value 'tags.0' is not an array, string found."];
        yield 'wrong list item' => [fn (Row $row) => $row->list('tags', 'int'), "Row value '0' is not an int, string found."];
        yield 'list for map' => [fn (Row $row) => $row->map('tags', 'string'), "Row value 'tags' is not an object, array found."];
        yield 'wrong nullable' => [fn (Row $row) => $row->nullableInt('code'), "Row value 'code' is not an int, string found."];
    }

    /** @param \Closure(Row): mixed $read */
    #[DataProvider('broken')]
    public function testABrokenValueThrowsNamingTheKey(\Closure $read, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);
        $read(self::row());
    }

    public function testDecodedJson(): void
    {
        $this->assertSame(1, Row::of(['a' => 1])->int('a'));
        $this->assertSame(['x', 'y'], array_map(fn (Row $row) => $row->string('n'), Row::each([['n' => 'x'], ['n' => 'y']])));
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected an array for a row, string found.');
        Row::of('text');
    }

    public function testEachWantsAList(): void
    {
        $this->expectException(UnexpectedValueException::class);
        Row::each(['a' => ['n' => 1]]);
    }
}
