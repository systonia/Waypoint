<?php

namespace Waypoint\Testing;

use PHPUnit\Framework\Assert;

/** What one TestRequest produced: status, headers, cookies, body, plus fluent assertions. */
final class TestResponse
{
    /** @var array<string, string> lower-cased name => value (last one wins) */
    private array $headers = [];

    /** @var list<string> Raw Set-Cookie values, in order. */
    private array $cookies = [];

    /** @param list<string> $rawHeaders "Name: value" lines as the SAPI sent them */
    public function __construct(private int $status, array $rawHeaders, private string $body)
    {
        foreach ($rawHeaders as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2) + [1 => '']);
            if (strcasecmp($name, 'Set-Cookie') === 0) {
                $this->cookies[] = $value;
            } else {
                $this->headers[strtolower($name)] = $value;
            }
        }
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, string> lower-cased name => value */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @return list<string> Raw Set-Cookie values. */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /** The value a Set-Cookie for $name carried, decoded; null if none was sent. */
    public function cookie(string $name): ?string
    {
        foreach (array_reverse($this->cookies) as $raw) {
            [$pair] = explode(';', $raw, 2);
            [$cookieName, $value] = explode('=', $pair, 2) + [1 => ''];
            if (rawurldecode($cookieName) === $name) {
                return rawurldecode($value);
            }
        }
        return null;
    }

    /** The decoded JSON body, or the value at a dot path inside it ("errors.email"). */
    public function json(?string $path = null): mixed
    {
        $data = json_decode($this->body, true);
        if ($path === null) {
            return $data;
        }
        foreach (explode('.', $path) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }
        return $data;
    }

    public function assertStatus(int $status): self
    {
        Assert::assertSame($status, $this->status, "Expected status $status, got {$this->status}. Body: " . substr($this->body, 0, 300));
        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    /** A 3xx to $location (any 3xx when $location is null). */
    public function assertRedirect(?string $location = null, ?int $status = null): self
    {
        Assert::assertTrue($this->status >= 300 && $this->status < 400, "Expected a redirect, got {$this->status}.");
        if ($status !== null) {
            $this->assertStatus($status);
        }
        if ($location !== null) {
            Assert::assertSame($location, $this->header('Location'));
        }
        return $this;
    }

    public function assertHeader(string $name, ?string $value = null): self
    {
        Assert::assertArrayHasKey(strtolower($name), $this->headers, "Header $name was not sent.");
        if ($value !== null) {
            Assert::assertSame($value, $this->header($name));
        }
        return $this;
    }

    public function assertHeaderMissing(string $name): self
    {
        Assert::assertArrayNotHasKey(strtolower($name), $this->headers, "Header $name was sent.");
        return $this;
    }

    /**
     * Content-Type starts with $prefix ("application/json" also matches "application/json; charset=utf-8").
     * @param non-empty-string $prefix
     */
    public function assertContentType(string $prefix): self
    {
        Assert::assertStringStartsWith($prefix, $this->header('Content-Type') ?? '');
        return $this;
    }

    public function assertCookie(string $name, ?string $value = null): self
    {
        Assert::assertNotNull($this->cookie($name), "No Set-Cookie for $name.");
        if ($value !== null) {
            Assert::assertSame($value, $this->cookie($name));
        }
        return $this;
    }

    public function assertSee(string $text): self
    {
        Assert::assertStringContainsString($text, $this->body);
        return $this;
    }

    public function assertDontSee(string $text): self
    {
        Assert::assertStringNotContainsString($text, $this->body);
        return $this;
    }

    public function assertBody(string $exact): self
    {
        Assert::assertSame($exact, $this->body);
        return $this;
    }

    /**
     * The whole decoded JSON body equals $expected.
     * @param array<array-key, mixed> $expected
     */
    public function assertJson(array $expected): self
    {
        Assert::assertSame($expected, $this->json());
        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): self
    {
        Assert::assertSame($expected, $this->json($path), "JSON path '$path' mismatch. Body: " . substr($this->body, 0, 300));
        return $this;
    }
}
