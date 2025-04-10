<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Tests\Server\Transport;

use JsonException;
use ModelContextProtocol\SDK\Exceptions\McpError;
use ModelContextProtocol\SDK\Server\Transport\SseServerTransport;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 * @covers \ModelContextProtocol\SDK\Server\Transport\SseServerTransport
 */
class SseServerTransportTest extends TestCase
{
    /**
     * 测试 SseServerTransport 构造函数和基础属性.
     */
    public function testConstructorAndProperties()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        // 使用反射验证属性设置
        $reflection = new ReflectionClass($transport);

        $endpointProp = $reflection->getProperty('endpoint');
        $endpointProp->setAccessible(true);
        $this->assertEquals($endpoint, $endpointProp->getValue($transport));

        $responseProp = $reflection->getProperty('response');
        $responseProp->setAccessible(true);
        $this->assertEquals($response, $responseProp->getValue($transport));

        // 验证会话 ID 已生成
        $this->assertNotEmpty($transport->getSessionId());
        $this->assertIsString($transport->getSessionId());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $transport->getSessionId());
    }

    /**
     * 测试回调设置方法.
     */
    public function testCallbackSetters()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        // 测试 setOnMessage
        $onMessageCallback = function () {
        };
        $transport->setOnMessage($onMessageCallback);
        $reflection = new ReflectionClass($transport);
        $onMessageProp = $reflection->getProperty('onMessage');
        $onMessageProp->setAccessible(true);
        $this->assertSame($onMessageCallback, $onMessageProp->getValue($transport));

        // 测试 setOnClose
        $onCloseCallback = function () {
        };
        $transport->setOnClose($onCloseCallback);
        $onCloseProp = $reflection->getProperty('onClose');
        $onCloseProp->setAccessible(true);
        $this->assertSame($onCloseCallback, $onCloseProp->getValue($transport));

        // 测试 setOnError
        $onErrorCallback = function () {
        };
        $transport->setOnError($onErrorCallback);
        $onErrorProp = $reflection->getProperty('onError');
        $onErrorProp->setAccessible(true);
        $this->assertSame($onErrorCallback, $onErrorProp->getValue($transport));
    }

    /**
     * 测试 handleMessage 方法处理有效消息.
     */
    public function testHandleMessageWithValidMessage()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        $messageReceived = null;
        $transport->setOnMessage(function ($msg) use (&$messageReceived) {
            $messageReceived = $msg;
        });

        // 模拟输出缓冲区
        ob_start();
        $result = $transport->handleMessage('{"type":"test","data":"hello"}');
        $output = ob_get_clean();

        $this->assertTrue($result);
        $this->assertEquals(['type' => 'test', 'data' => 'hello'], $messageReceived);
        $this->assertStringContainsString('Accepted', $output);
    }

    /**
     * 测试 handleMessage 方法处理无效消息.
     */
    public function testHandleMessageWithInvalidMessage()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        $errorReceived = null;
        $transport->setOnError(function ($error) use (&$errorReceived) {
            $errorReceived = $error;
        });

        // 模拟输出缓冲区
        ob_start();
        $result = $transport->handleMessage('{"invalid json');
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertNotNull($errorReceived);
        $this->assertInstanceOf(JsonException::class, $errorReceived);
        $this->assertStringContainsString('Invalid message', $output);
    }

    /**
     * 测试 handleMessage 方法处理空消息.
     */
    public function testHandleMessageWithNullMessage()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        $result = $transport->handleMessage(null);

        $this->assertFalse($result);
    }

    /**
     * 测试 handlePostMessage 方法的内容类型验证.
     */
    public function testHandlePostMessageContentTypeValidation()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        // 通过反射设置 sseResponse 属性，以便测试不会抛出"连接未建立"异常
        $reflection = new ReflectionClass($transport);
        $sseResponseProp = $reflection->getProperty('sseResponse');
        $sseResponseProp->setAccessible(true);
        $sseResponseProp->setValue($transport, $response);

        $errorReceived = null;
        $transport->setOnError(function ($error) use (&$errorReceived) {
            $errorReceived = $error;
        });

        // 测试无效的内容类型
        ob_start();
        $result = $transport->handlePostMessage('{"type":"test"}', 'text/plain');
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertNotNull($errorReceived);
        $this->assertInstanceOf(McpError::class, $errorReceived);
        $this->assertStringContainsString('Unsupported content-type', $output);
    }

    /**
     * 测试 generateUuid 方法生成有效的 UUID v4.
     */
    public function testGenerateUuid()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        $reflection = new ReflectionClass($transport);
        $generateUuidMethod = $reflection->getMethod('generateUuid');
        $generateUuidMethod->setAccessible(true);

        $uuid = $generateUuidMethod->invoke($transport);

        // 验证格式符合 UUID v4 标准
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    /**
     * 测试关闭连接时回调函数被调用.
     */
    public function testCloseCallsOnCloseCallback()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        // 通过反射设置 sseResponse 属性，以便测试 close 方法
        $reflection = new ReflectionClass($transport);
        $sseResponseProp = $reflection->getProperty('sseResponse');
        $sseResponseProp->setAccessible(true);
        $sseResponseProp->setValue($transport, $response);

        $closeCalled = false;
        $transport->setOnClose(function () use (&$closeCalled) {
            $closeCalled = true;
        });

        $transport->close();

        $this->assertTrue($closeCalled);
        // 验证 sseResponse 已被设置为 null
        $this->assertNull($sseResponseProp->getValue($transport));
    }

    /**
     * 测试不调用 close 两次.
     */
    public function testCloseIsIdempotent()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        // 通过反射设置 sseResponse 属性
        $reflection = new ReflectionClass($transport);
        $sseResponseProp = $reflection->getProperty('sseResponse');
        $sseResponseProp->setAccessible(true);
        $sseResponseProp->setValue($transport, $response);

        $closeCount = 0;
        $transport->setOnClose(function () use (&$closeCount) {
            ++$closeCount;
        });

        // 调用 close 两次
        $transport->close();
        $transport->close();

        // onClose 回调应该只被调用一次
        $this->assertEquals(1, $closeCount);
    }

    /**
     * 测试在未连接状态下发送消息时会抛出异常.
     */
    public function testSendThrowsExceptionWhenNotConnected()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Not connected');

        $transport->writeMessage('test message');
    }

    /**
     * 测试在未建立连接时处理 POST 消息抛出异常.
     */
    public function testHandlePostMessageThrowsExceptionWhenNotStarted()
    {
        $endpoint = 'http://example.com/mcp-endpoint';
        $response = fopen('php://memory', 'w+');

        $transport = new SseServerTransport($endpoint, $response);

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('SSE connection not established');

        // 捕获输出
        ob_start();
        try {
            $transport->handlePostMessage('{"type":"test"}', 'application/json');
        } catch (McpError $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
