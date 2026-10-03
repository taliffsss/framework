<?php

declare(strict_types=1);

namespace Naluz\Tests\Messaging;

use Naluz\Messaging\Codec;
use Naluz\Messaging\InvalidMessageException;
use Naluz\Messaging\Message;
use Naluz\Messaging\MessagingException;
use PHPUnit\Framework\TestCase;

final class CodecTest extends TestCase
{
    private function message(): Message
    {
        return new Message('id-1', 'orders.placed', 'App\\Events\\OrderPlaced', ['order_id' => 7, 'items' => [['sku' => 'a']]], ['x-attempts' => '2'], 1700000000);
    }

    public function testUnsignedRoundTripIsPlainJson(): void
    {
        $codec = new Codec();
        $body = $codec->encode($this->message());
        $this->assertSame(7, json_decode($body, true)['payload']['order_id'], 'a plain envelope other services can read');
        $back = $codec->decode($body, 'orders.placed');
        $this->assertSame('id-1', $back->id);
        $this->assertSame(['order_id' => 7, 'items' => [['sku' => 'a']]], $back->payload);
        $this->assertSame(2, $back->attempts());
        $this->assertSame(1700000000, $back->timestamp);
        $this->assertSame('App\\Events\\OrderPlaced', $back->type);
    }

    public function testEmptyPayloadStaysAnObject(): void
    {
        $body = (new Codec())->encode(new Message('i', 't', 't', []));
        $this->assertStringContainsString('"payload":{}', $body);
    }

    public function testSignedRoundTripAndTamperDetection(): void
    {
        $codec = new Codec('s3cret');
        $this->assertTrue($codec->signs());
        $body = $codec->encode($this->message());
        $this->assertSame(7, $codec->decode($body, 't')->payload['order_id']);

        $outer = json_decode($body, true);
        $outer['data'] = str_replace('"order_id":7', '"order_id":8', $outer['data']);
        $this->expectException(InvalidMessageException::class);
        $codec->decode(json_encode($outer), 't');
    }

    public function testSignedConsumerRejectsUnsignedMessages(): void
    {
        $unsigned = (new Codec())->encode($this->message());
        $this->expectException(InvalidMessageException::class);
        (new Codec('s3cret'))->decode($unsigned, 't');
    }

    public function testWrongKeyIsRejectedAndPreviousKeyIsAccepted(): void
    {
        $body = (new Codec('old'))->encode($this->message());
        try {
            (new Codec('new'))->decode($body, 't');
            $this->fail('a different key must not verify');
        } catch (InvalidMessageException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame('id-1', (new Codec('new', ['old']))->decode($body, 't')->id);
    }

    public function testOversizeAndMalformedAreRejected(): void
    {
        $codec = new Codec('', [], 100);
        foreach (['not json', '"a string"', '[', str_repeat('x', 101)] as $bad) {
            try {
                $codec->decode($bad, 't');
                $this->fail("accepted: {$bad}");
            } catch (InvalidMessageException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(MessagingException::class);
        $codec->encode(new Message('i', 't', 't', ['blob' => str_repeat('x', 200)]));
    }

    public function testDeeplyNestedJsonIsRejected(): void
    {
        $this->expectException(InvalidMessageException::class);
        (new Codec('', [], 1_000_000, 8))->decode(str_repeat('[', 50) . str_repeat(']', 50), 't');
    }

    public function testBareJsonFromAnotherServiceBecomesThePayload(): void
    {
        $m = (new Codec())->decode('{"order_id": 9}', 'orders.placed');
        $this->assertSame(['order_id' => 9], $m->payload);
        $this->assertSame('orders.placed', $m->type);
        $this->assertNotSame('', $m->id);
    }
}
