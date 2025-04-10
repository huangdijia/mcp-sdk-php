<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Tests\Client\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use ModelContextProtocol\SDK\Client\Transport\SseClientTransport;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 * @coversNothing
 */
class SseClientTransportTest extends TestCase
{
    /**
     * Test that the transport can connect and receive SSE events.
     */
    public function testReceiveEvents()
    {
        // Create a mock stream that will return SSE formatted data
        $mockBody = fopen('php://memory', 'r+');
        fwrite($mockBody, "data: {\"message\":\"Hello\"}\n\n");
        fwrite($mockBody, "data: {\"message\":\"World\"}\n\n");
        rewind($mockBody);

        // Create a mock response with the stream
        $mockResponse = new Response(200, ['Content-Type' => 'text/event-stream'], new Stream($mockBody));

        // Create a mock handler that returns the response
        $mockHandler = new MockHandler([$mockResponse]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        // Create a reflection of the SseClientTransport class to access protected properties
        $transportReflection = new ReflectionClass(SseClientTransport::class);

        // Create an instance of SseClientTransport with constructor arguments
        $transport = $transportReflection->newInstanceWithoutConstructor();

        // Set the client property using reflection
        $clientProperty = $transportReflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($transport, $mockClient);

        // Set the url property using reflection
        $urlProperty = $transportReflection->getProperty('url');
        $urlProperty->setAccessible(true);
        $urlProperty->setValue($transport, 'http://example.com/sse');

        // Set the active property using reflection
        $activeProperty = $transportReflection->getProperty('active');
        $activeProperty->setAccessible(true);
        $activeProperty->setValue($transport, true);

        // Create a mock for the onmessage callback
        $receivedMessages = [];
        $transport->setOnMessage(function ($message) use (&$receivedMessages) {
            $receivedMessages[] = $message;
        });

        // Call the connect method using reflection
        $connectMethod = $transportReflection->getMethod('connect');
        $connectMethod->setAccessible(true);
        $connectMethod->invoke($transport);

        // Assert that messages were received
        $this->assertCount(2, $receivedMessages);
        $this->assertEquals('{"message":"Hello"}', $receivedMessages[0]);
        $this->assertEquals('{"message":"World"}', $receivedMessages[1]);
    }

    /**
     * Test that the transport can send messages.
     */
    public function testSendMessage()
    {
        // Create a mock client that records the request
        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);

        $mockHandler = new MockHandler([
            new Response(200),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push($history);

        $mockClient = new Client(['handler' => $handlerStack]);

        // Create a transport with the mock client
        $transport = new SseClientTransport('http://example.com/sse');

        // Set the client property using reflection
        $reflection = new ReflectionClass($transport);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($transport, $mockClient);

        // Send a message
        $message = '{"type":"request","id":"123","method":"add","params":{"a":5,"b":3}}';
        $transport->writeMessage($message);

        // Assert that the request was made correctly
        $this->assertCount(1, $container);
        $request = $container[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertEquals($message, (string) $request->getBody());
    }

    /**
     * Test that the transport can be closed.
     */
    public function testClose()
    {
        // Create a transport
        $transport = new SseClientTransport('http://example.com/sse');

        // Set up a flag to check if onclose was called
        $closeCalled = false;
        $transport->setOnClose(function () use (&$closeCalled) {
            $closeCalled = true;
        });

        // Close the transport
        $transport->stop();

        // Assert that onclose was called
        $this->assertTrue($closeCalled);

        // Assert that the transport is no longer active
        $reflection = new ReflectionClass($transport);
        $activeProperty = $reflection->getProperty('active');
        $activeProperty->setAccessible(true);
        $this->assertFalse($activeProperty->getValue($transport));
    }
}
