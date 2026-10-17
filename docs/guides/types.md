---
title: Types and PHPStan
description: Name every type once, let PHPStan read Waypoint's attributes, and read database rows and decoded JSON with Row.
order: 145
---

Waypoint is checked at PHPStan level 10, and an app can be too. The rule that keeps this cheap: every fact about a type is written in one place. A type in an attribute is not repeated in a docblock, a native type is not repeated at all.

## One place per fact

1. **Native types first.** An array shape such as `array{code: int, max: float, rain: int}` becomes a small readonly class. Its properties carry native types and need no docblock (the Bakery's `src/Models/WeatherDay.php`):

   ```php
   /** One day's forecast: the WMO weather code, the highest temperature in °C and the chance of rain in percent. */
   final readonly class WeatherDay
   {
       public function __construct(
           public int $code,
           public float $max,
           public int $rain,
       ) {
       }
   }
   ```

2. **Arrays Waypoint sees** (DTO properties, route results): the attribute only. `#[ListOf]` on a property and `#[Returns]` on a route already name the elements for binding and [OpenAPI](openapi.md); Waypoint's PHPStan extension turns them into real types, so there is no `@var` or `@return` next to them. From the Bakery's `OrderSummary` and `ApiController`:

   ```php
   final readonly class OrderSummary
   {
       public function __construct(
           public string $code,
           public OrderStatus $status,
           #[ListOf(OrderLine::class)]
           public array $lines,
       ) {
       }
   }

   #[Get('/products')]
   #[Summary('The range with prices in cents and what is left')]
   #[Returns(
       type: Product::class,
       list: true
   )]
   public function products(): array
   {
       return $this->products->all();
   }
   ```

3. **Arrays Waypoint does not see** (domain classes, repositories): the PHPDoc type only.

   ```php
   /** @return list<Order> everything not picked up or cancelled yet, oldest pickup first */
   public function open(): array
   ```

4. **Description text** in a docblock only when it says more than the name. Routes have no docblock: `#[Summary]` and `#[Returns(description: ...)]` are their documentation.

5. **Trust boundaries** (database rows, external JSON): read through `Waypoint\Support\Row` with typed getters, not with casts and not by spreading `...$row` into a constructor. See [Row](#row) below.

## The PHPStan extension

`extension.neon` in the package is loaded automatically with `phpstan/extension-installer`; without it, add `vendor/systonia/waypoint/extension.neon` to `includes`. It does four things:

- An `#[Inject]` property counts as written and initialized: the container fills it.
- `#[ListOf]` and `#[Returns]` satisfy "no value type specified in iterable type array": PHPStan asks for no docblock with them.
- Reading a `#[ListOf]` property and calling a `#[Returns]` method has the attribute's type: `list<OrderLine>`, `array<string, string>` with `map: true`, and `T` itself for `#[Returns(T::class)]` without `list` or `map`. The type is nullable when the native type is. An attribute that does not narrow the native type (a method typed `Response` whose `#[Returns]` documents the body) leaves the native type alone.
- What goes in must fit. These are reported:

  | Identifier | When |
  |---|---|
  | `waypoint.returns` | a `return` in a `#[Returns]` method gives something else than the attribute's type |
  | `waypoint.listOf` | an assignment to a `#[ListOf]` property, or a constructor argument of a promoted `#[ListOf]` parameter, does not fit |

  ```php
  #[Returns(
      type: Product::class,
      list: true
  )]
  public function products(): array
  {
      return $this->db->query('SELECT * FROM products');
      // Method ApiController::products() should return list<Product> as #[Returns] says, list<array<string, mixed>> returned.
  }
  ```

## Row

A database row or decoded JSON is `mixed` to PHPStan, and rightly so: nothing in the code guarantees its shape. `Row` reads it with typed getters that throw when the value is missing or of another type:

The Bakery's `Orders` repository builds an `Order` from a row this way, its lines from a `json_agg()` column:

```php
use Waypoint\Support\Row;

$row = new Row($this->db->first(self::SELECT . ' WHERE o.code = $1', [$code]) ?? throw new NotFoundException("No order $code"));

return new Order(
    id: $row->int('id'),
    code: $row->string('code'),
    customerName: $row->string('customerName'),
    pickupDate: $row->string('pickupDate'),
    status: $row->enum('status', OrderStatus::class),
    lines: array_map(
        fn (Row $line) => new OrderLine($line->int('product'), $line->string('name'), $line->int('priceCents'), $line->int('quantity')),
        $row->rows('lines'),
    ),
);
```

| Getter | Returns |
|---|---|
| `int()`, `string()`, `float()`, `bool()` | the value as that type |
| `enum($key, Status::class)` | the backed enum case the value names |
| `nullableInt()`, `nullableString()`, `nullableFloat()`, `nullableBool()`, `nullableEnum()`, `nullableRow()` | the same, or null for a null value or a missing key |
| `row($key)` | a nested object as a `Row` |
| `rows($key)` | a nested list of objects, `list<Row>` |
| `list($key, 'string')` | a nested list of `int`, `string`, `float` or `bool` values |
| `map($key, 'string')` | a nested object of such values by string key |
| `has($key)` | whether the key exists |
| `Row::of($json)` | decoded JSON as a `Row`; throws when it is no array |
| `Row::each($json)` | decoded JSON that is a list of objects, `list<Row>` |

Values are not converted, with one exception: `float()` also takes an int and a numeric string, since Postgres sends a `numeric` that a float cannot hold exactly as text. A broken value throws an `UnexpectedValueException` that names the key and the type found (`Row value 'id' is not an int, string found.`): a broken row is a program error, not a validation error. For request input, use [binding and validation](binding.md) instead.

External JSON that may be broken is read the same way, with the exception caught where the app can carry on without it. The Bakery's `Weather` service reads the forecast of open-meteo, and the product page goes on without it when the answer has another shape:

```php
try {
    $daily = Row::of($response->json())->row('daily');
    $codes = $daily->list('weather_code', 'int');
    $max = $daily->list('temperature_2m_max', 'float');
    $rain = $daily->list('precipitation_probability_max', 'int');
    $week = [];
    foreach ($daily->list('time', 'string') as $i => $day) {
        $week[$day] = new WeatherDay($codes[$i] ?? 0, $max[$i] ?? 0.0, $rain[$i] ?? 0);
    }
    return $week;
} catch (UnexpectedValueException) {
    return null;
}
```
