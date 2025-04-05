<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Tests\Shared;

use ModelContextProtocol\SDK\Shared\AbortController;
use ModelContextProtocol\SDK\Shared\AbortSignal;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \ModelContextProtocol\SDK\Shared\AbortController
 */
class AbortControllerTest extends TestCase
{
    /**
     * 测试 AbortController 初始化.
     */
    public function testInitialization()
    {
        $controller = new AbortController();
        $signal = $controller->signal();

        $this->assertInstanceOf(AbortSignal::class, $signal);
        $this->assertFalse($signal->aborted());
    }

    /**
     * 测试中止控制器会将信号状态设置为已中止.
     */
    public function testAbort()
    {
        $controller = new AbortController();
        $signal = $controller->signal();

        $controller->abort();
        $this->assertTrue($signal->aborted());
    }

    /**
     * 测试信号的回调函数在控制器中止时会被触发.
     */
    public function testAbortCallback()
    {
        $controller = new AbortController();
        $signal = $controller->signal();

        $callbackCalled = false;
        $signal->onabort = function () use (&$callbackCalled) {
            $callbackCalled = true;
        };

        $controller->abort();
        $this->assertTrue($callbackCalled);
    }
}
