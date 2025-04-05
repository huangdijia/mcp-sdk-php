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

use ModelContextProtocol\SDK\Shared\AbortSignal;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \ModelContextProtocol\SDK\Shared\AbortSignal
 */
class AbortSignalTest extends TestCase
{
    /**
     * 测试初始状态下中止信号未被触发.
     */
    public function testInitialAbortState()
    {
        $signal = new AbortSignal();
        $this->assertFalse($signal->aborted());
    }

    /**
     * 测试中止信号触发后的状态.
     */
    public function testAbortedState()
    {
        $signal = new AbortSignal();
        $signal->abort();
        $this->assertTrue($signal->aborted());
    }

    /**
     * 测试中止信号的回调函数.
     */
    public function testOnAbortCallback()
    {
        $signal = new AbortSignal();
        $callbackCalled = false;

        $signal->onabort = function () use (&$callbackCalled) {
            $callbackCalled = true;
        };

        $signal->abort();
        $this->assertTrue($callbackCalled);
    }

    /**
     * 测试重复触发中止信号不会导致回调函数被多次调用.
     */
    public function testMultipleAbortCallsOnlyCausesOneCallback()
    {
        $signal = new AbortSignal();
        $callCount = 0;

        $signal->onabort = function () use (&$callCount) {
            ++$callCount;
        };

        $signal->abort();
        $signal->abort();
        $signal->abort();

        $this->assertEquals(1, $callCount);
    }
}
