<?php

namespace Waypoint\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Waypoint\Http\{Response, ResultRenderer};

final class ResultRendererTest extends TestCase
{
    private static function xml(mixed $result): string
    {
        $res = new Response();
        (new ResultRenderer())->render($result, $res, ['type' => 'xml']);
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
        ob_start();
        $res->send();
        return (string) ob_get_clean();
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
