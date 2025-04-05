<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Tests\Server;

use ModelContextProtocol\SDK\Exceptions\McpError;
use ModelContextProtocol\SDK\Server\Server;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 * @covers \ModelContextProtocol\SDK\Server\Server
 */
class ServerTest extends TestCase
{
    /**
     * 测试服务器初始化.
     */
    public function testServerInitialization()
    {
        $serverInfo = ['name' => 'TestServer', 'version' => '1.0.0'];
        $capabilities = ['streamingSupport' => true];

        $server = new Server($serverInfo, ['capabilities' => $capabilities]);

        $reflection = new ReflectionClass($server);

        $serverInfoProp = $reflection->getProperty('serverInfo');
        $serverInfoProp->setAccessible(true);
        $this->assertEquals($serverInfo, $serverInfoProp->getValue($server));

        $capabilitiesProp = $reflection->getProperty('capabilities');
        $capabilitiesProp->setAccessible(true);
        $this->assertEquals($capabilities, $capabilitiesProp->getValue($server));
    }

    /**
     * 测试服务器处理初始化请求.
     */
    public function testHandleInitializeRequest()
    {
        $serverInfo = ['name' => 'TestServer', 'version' => '1.0.0'];
        $serverCapabilities = ['streamingSupport' => true];
        $server = new Server($serverInfo, ['capabilities' => $serverCapabilities]);

        $clientInfo = ['name' => 'TestClient', 'version' => '1.0.0'];
        $clientCapabilities = ['structuredOutputSupport' => true];

        $params = [
            'protocolVersion' => '0.1.0',
            'clientInfo' => $clientInfo,
            'capabilities' => $clientCapabilities,
        ];

        $result = $server->handleInitialize($params);

        $this->assertEquals($serverInfo, $result['serverInfo']);
        $this->assertEquals($serverCapabilities, $result['capabilities']);
        $this->assertTrue($server->getIsInitialized());
        $this->assertEquals($clientInfo, $server->getClientVersion());
        $this->assertEquals($clientCapabilities, $server->getClientCapabilities());
    }

    /**
     * 测试服务器处理初始化请求时的协议版本验证.
     */
    public function testHandleInitializeWithInvalidProtocolVersion()
    {
        $serverInfo = ['name' => 'TestServer', 'version' => '1.0.0'];
        $server = new Server($serverInfo);

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Unsupported protocol version: invalid');

        $server->handleInitialize([
            'protocolVersion' => 'invalid',
        ]);
    }

    /**
     * 测试重复初始化服务器.
     */
    public function testRepeatedInitialization()
    {
        $serverInfo = ['name' => 'TestServer', 'version' => '1.0.0'];
        $server = new Server($serverInfo);

        // 首次初始化应成功
        $params = [
            'protocolVersion' => '0.1.0',
            'clientInfo' => ['name' => 'TestClient', 'version' => '1.0.0'],
        ];
        $server->handleInitialize($params);

        // 重复初始化应失败
        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Server already initialized');
        $server->handleInitialize($params);
    }

    /**
     * 测试客户端能力检查.
     */
    public function testClientCapabilityCheck()
    {
        $serverInfo = ['name' => 'TestServer', 'version' => '1.0.0'];
        $server = new Server($serverInfo);

        $params = [
            'protocolVersion' => '0.1.0',
            'clientInfo' => ['name' => 'TestClient', 'version' => '1.0.0'],
            'capabilities' => [
                'streamingSupport' => true,
                'structuredOutputSupport' => false,
            ],
        ];

        $server->handleInitialize($params);

        $this->assertTrue($server->clientSupports('streamingSupport'));
        $this->assertFalse($server->clientSupports('structuredOutputSupport'));
        $this->assertFalse($server->clientSupports('nonexistentCapability'));
    }

    /**
     * 测试初始化回调.
     */
    public function testInitializedCallback()
    {
        $serverInfo = ['name' => 'TestServer', 'version' => '1.0.0'];
        $server = new Server($serverInfo);

        $callbackCalled = false;
        $server->onInitialized = function () use (&$callbackCalled) {
            $callbackCalled = true;
        };

        $params = [
            'protocolVersion' => '0.1.0',
        ];

        $server->handleInitialize($params);
        $this->assertTrue($callbackCalled);
    }

    /**
     * 测试服务器指令功能.
     */
    public function testServerInstructions()
    {
        $serverInfo = ['name' => 'TestServer', 'version' => '1.0.0'];
        $instructions = '这是测试服务器的指令。';

        $server = new Server($serverInfo, ['instructions' => $instructions]);

        $params = [
            'protocolVersion' => '0.1.0',
        ];

        $result = $server->handleInitialize($params);
        $this->assertEquals($instructions, $result['instructions']);
    }
}
