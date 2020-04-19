<?php namespace Loilo\GithubWebhook\Test;

use Loilo\GithubWebhook\Delivery;
use PHPUnit\Framework\TestCase;

class DeliveryTest extends TestCase
{
    public function testEventGetter()
    {
        $delivery = new Delivery('foo', []);

        $this->assertSame('foo', $delivery->event());
    }

    public function testPayloadGetter()
    {
        $payload = [
            'a' => '1',
            'b' => [
                'c' => '2'
            ],
            'd' => [ '3' ]
        ];
        $delivery = new Delivery('foo', $payload);

        // Omitted path should return the whole payload
        $this->assertSame($payload, $delivery->payload());

        // Access should be possible through dot-delimited paths
        $this->assertSame('1', $delivery->payload('a'));
        $this->assertSame([ 'c' => '2' ], $delivery->payload('b'));
        $this->assertSame('2', $delivery->payload('b.c'));
        $this->assertSame('3', $delivery->payload('d.0'));

        // Index accessors should only work on arrays, not on strings
        $this->assertNull($delivery->payload('a.0'));
        $this->assertNull($delivery->payload('a.foo'));
    }
}
