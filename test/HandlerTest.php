<?php

namespace Loilo\GithubWebhook\Test;

use GuzzleHttp\Psr7\ServerRequest;
use Loilo\GithubWebhook\Delivery;
use Loilo\GithubWebhook\Exceptions;
use Loilo\GithubWebhook\Handler;
use PHPUnit\Framework\TestCase;

class HandlerTest extends TestCase
{
    const DEFAULT_SECRET = '12345';

    /**
     * Create a basic example webhook request
     * It's very reduced but fully compatible with regular webhook requests
     * The secret use for signature generation is the DEFAULT_SECRET
     */
    private function createRequest(): ServerRequest
    {
        return new ServerRequest(
            'POST',
            'https://localhost/',
            [
                'Accept' => '*/*',
                'content-type' => 'application/json',
                'User-Agent' => 'GitHub-Hookshot/0000000',
                'X-GitHub-Delivery' => '00000000-0000-0000-0000-000000000000',
                'X-GitHub-Event' => 'ping',
                'X-Hub-Signature' =>
                    'sha1=5563c89a2f4743278567358293adc9a65680725b',
            ],
            '{"zen":"Approachable is better than simple.","hook_id":202756468,"sender":{"url":"https://api.github.com/users/loilo"}}'
        );
    }

    public function testHandleBasicRequest()
    {
        $handler = new Handler($this::DEFAULT_SECRET);
        $request = $this->createRequest();
        $delivery = $handler->handle($request);

        $this->assertInstanceOf(Delivery::class, $delivery);
        $this->assertSame(
            json_decode($request->getBody(), true),
            $delivery->payload()
        );
        $this->assertSame('ping', $delivery->event());
    }

    public function testHandleNoSecret()
    {
        $handler = new Handler();
        $request = $this->createRequest()->withoutHeader('X-Hub-Signature');
        $delivery = $handler->handle($request);

        $this->assertInstanceOf(Delivery::class, $delivery);
    }

    public function testHandleMd5Signature()
    {
        $handler = new Handler($this::DEFAULT_SECRET);
        $request = $this->createRequest()->withHeader(
            'X-Hub-Signature',
            'md5=7ee5ad26520d41969c11f09b6f59b0f3'
        );
        $delivery = $handler->handle($request);

        $this->assertInstanceOf(Delivery::class, $delivery);
    }

    public function testHandleFormDataRequest()
    {
        $handler = new Handler($this::DEFAULT_SECRET);
        $request = $this->createRequest();
        $request = $request
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody([
                'payload' => (string) $request->getBody(),
            ]);
        $delivery = $handler->handle($request);

        $this->assertInstanceOf(Delivery::class, $delivery);
    }

    public function testInvalidMethod()
    {
        $this->expectException(Exceptions\InvalidMethodException::class);

        $handler = new Handler($this::DEFAULT_SECRET);
        $request = $this->createRequest()->withMethod('GET');
        $handler->handle($request);
    }

    public function testMissingContentTypeHeader()
    {
        $this->expectException(Exceptions\MissingHeaderException::class);

        $handler = new Handler($this::DEFAULT_SECRET);
        $request = $this->createRequest()->withoutHeader('Content-Type');
        $handler->handle($request);
    }

    public function testMissingXGitHubEventHeader()
    {
        $this->expectException(Exceptions\MissingHeaderException::class);

        $handler = new Handler($this::DEFAULT_SECRET);
        $request = $this->createRequest()->withoutHeader('X-GitHub-Event');
        $handler->handle($request);
    }

    public function testMissingXGitHubDeliveryHeader()
    {
        $this->expectException(Exceptions\MissingHeaderException::class);

        $handler = new Handler($this::DEFAULT_SECRET);
        $request = $this->createRequest()->withoutHeader('X-GitHub-Delivery');
        $handler->handle($request);
    }

    public function testMissingXHubSignatureHeader()
    {
        $this->expectException(Exceptions\MissingHeaderException::class);

        $handler = new Handler($this::DEFAULT_SECRET);
        $request = $this->createRequest()->withoutHeader('X-Hub-Signature');
        $handler->handle($request);
    }

    public function testInvalidContentType()
    {
        $this->expectException(Exceptions\InvalidContentTypeException::class);

        $handler = new Handler($this::DEFAULT_SECRET);
        $request = $this->createRequest()->withHeader(
            'Content-Type',
            'foo/bar'
        );
        $handler->handle($request);
    }

    public function testUnsupportedHashingFunction()
    {
        $this->expectException(Exceptions\ServerException::class);

        $handler = new Handler($this::DEFAULT_SECRET);
        $request = $this->createRequest()->withHeader(
            'X-Hub-Signature',
            'foo=bar'
        );
        $handler->handle($request);
    }

    public function testInvalidSecret()
    {
        $this->expectException(Exceptions\InvalidSecretException::class);

        $handler = new Handler($this::DEFAULT_SECRET . 'foo');
        $request = $this->createRequest();
        $handler->handle($request);
    }

    public function testInvalidJsonPayload()
    {
        $this->expectException(Exceptions\InvalidPayloadException::class);

        // Omit secret to avoid invalid secret exception
        $handler = new Handler();
        $request = $this->createRequest();
        $request = new ServerRequest(
            $request->getMethod(),
            $request->getUri(),
            $request->getHeaders(),
            ((string) $request->getBody()) . '?'
        );
        $handler->handle($request);
    }

    public function testNonArrayPayload()
    {
        $this->expectException(Exceptions\InvalidPayloadException::class);

        // Omit secret to avoid invalid secret exception
        $handler = new Handler();
        $request = $this->createRequest();
        $request = new ServerRequest(
            $request->getMethod(),
            $request->getUri(),
            $request->getHeaders(),
            'null'
        );
        $handler->handle($request);
    }

    public function testMissingFormDataPayload()
    {
        $this->expectException(Exceptions\InvalidPayloadException::class);

        // Omit secret to avoid invalid secret exception
        $handler = new Handler();
        $request = $this->createRequest()
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody([]);
        $handler->handle($request);
    }
}
