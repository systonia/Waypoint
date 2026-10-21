<?php

namespace Waypoint\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Waypoint\Http\{Response, ResultRenderer};
use Waypoint\Tests\Fixtures\DTO\{BadgeDTO, CityDTO, ProfileDTO};
use Waypoint\Tests\Fixtures\Enums\OrderStatus;

final class ResultRendererTest extends TestCase
{
    private static function json(mixed $result): string
    {
        $res = new Response();
        (new ResultRenderer())->render($result, $res, []);
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
        ob_start();
        $res->send();
        return (string) ob_get_clean();
    }

    private static function profile(): ProfileDTO
    {
        $profile = new ProfileDTO(['name' => 'Ada']);
        $profile->passwordHash = 'secret';
        $profile->internalId = 7;
        $profile->friend = new ProfileDTO(['name' => 'Bob']);
        $profile->friend->passwordHash = 'secret too';
        return $profile;
    }

    public function testJsonLeavesIgnoredPropertiesOutAtEveryLevel(): void
    {
        $json = self::json(['me' => self::profile(), 'badges' => [new BadgeDTO(1, 'gold')], 'byKey' => ['x' => new BadgeDTO(2, 'red')]]);

        $this->assertSame('{"me":{"name":"Ada","friend":{"name":"Bob","friend":null,"errors":[],"isValid":true},"errors":[],"isValid":true},"badges":[{"label":"gold"}],"byKey":{"x":{"label":"red"}}}', $json);
    }

    public function testJsonWithoutIgnoreIsWhatJsonEncodeWrites(): void
    {
        $value = ['city' => new CityDTO(['city' => 'Freiburg']), 'status' => OrderStatus::Open, 'at' => new \DateTimeImmutable('2026-01-02 03:04:05', new \DateTimeZone('UTC')), 'empty' => new \stdClass(), 'list' => [1, 2], 'none' => []];

        $this->assertSame(json_encode($value), self::json($value));
    }

    public function testJsonFindsAnObjectDeepInPlainRows(): void
    {
        $json = self::json([['id' => 1], ['nested' => ['deeper' => [new BadgeDTO(2, 'gold')]]]]);

        $this->assertSame('[{"id":1},{"nested":{"deeper":[{"label":"gold"}]}}]', $json);
    }

    public function testXmlLeavesIgnoredPropertiesOut(): void
    {
        $document = simplexml_load_string(self::xml(self::profile()));

        $this->assertNotFalse($document);
        $this->assertSame('Ada', (string) $document->name);
        $this->assertSame('Bob', (string) $document->friend->name);
        $this->assertCount(0, $document->passwordHash);
        $this->assertCount(0, $document->friend->passwordHash);
        $this->assertCount(0, $document->internalId);
    }

    private static function xml(mixed $result): string
    {
        $res = new Response();
        (new ResultRenderer())->render($result, $res, ['type' => 'xml']);
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
        ob_start();
        $res->send();
        return (string) ob_get_clean();
    }

    public function testDataJsonCannotHoldThrowsInsteadOfBecomingNull(): void
    {
        $this->expectException(\JsonException::class);
        (new ResultRenderer())->render(['name' => "Caf\xE9"], new Response(), []);
    }

    public function testXmlEscapesTextInsteadOfBreakingTheDocument(): void
    {
        $xml = self::xml(['name' => 'Tom & Jerry <3', 'note' => '</name><admin>true</admin>']);

        $document = simplexml_load_string($xml);
        $this->assertNotFalse($document, $xml);
        $this->assertSame('Tom & Jerry <3', (string) $document->name);
        $this->assertSame('</name><admin>true</admin>', (string) $document->note);
        $this->assertCount(0, $document->admin);
    }

    public function testXmlKeepsNestingAndTurnsListIndexesIntoItems(): void
    {
        $xml = self::xml(['order' => ['id' => 7, 'lines' => [['sku' => 'A'], ['sku' => 'B']]], 'paid' => false, 'bad key' => 1]);

        $document = simplexml_load_string($xml);
        $this->assertNotFalse($document, $xml);
        $this->assertSame('7', (string) $document->order->id);
        $this->assertSame('A', (string) $document->order->lines->item[0]->sku);
        $this->assertSame('1', (string) $document->order->lines->item[1]['key']);
        $this->assertSame('B', (string) $document->order->lines->item[1]->sku);
        $this->assertSame('false', (string) $document->paid);
        $this->assertSame('bad key', (string) $document->item['key']);
    }
}
