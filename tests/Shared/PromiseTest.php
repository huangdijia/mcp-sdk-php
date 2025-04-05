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

use Exception;
use ModelContextProtocol\SDK\Shared\Promise;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \ModelContextProtocol\SDK\Shared\Promise
 */
class PromiseTest extends TestCase
{
    /**
     * 测试 Promise 成功解析.
     */
    public function testResolve()
    {
        $promise = new Promise();
        $result = null;

        $promise->then(function ($value) use (&$result) {
            $result = $value;
        });

        $promise->resolve('success');
        $this->assertEquals('success', $result);
    }

    /**
     * 测试 Promise 拒绝.
     */
    public function testReject()
    {
        $promise = new Promise();
        $error = null;

        $promise->catch(function ($reason) use (&$error) {
            $error = $reason;
        });

        $exception = new Exception('Failed');
        $promise->reject($exception);
        $this->assertSame($exception, $error);
    }

    /**
     * 测试 Promise 链式调用.
     */
    public function testChaining()
    {
        $promise = new Promise();
        $result = null;

        $promise
            ->then(function ($value) {
                return $value * 2;
            })
            ->then(function ($value) use (&$result) {
                $result = $value;
            });

        $promise->resolve(5);
        $this->assertEquals(10, $result);
    }

    /**
     * 测试 Promise 链式错误处理.
     */
    public function testChainingWithReject()
    {
        $promise = new Promise();
        $result = null;

        $promise
            ->then(function () {
                throw new Exception('Chain error');
            })
            ->catch(function () {
                return 'recovered';
            })
            ->then(function ($value) use (&$result) {
                $result = $value;
            });

        $promise->resolve('start');
        $this->assertEquals('recovered', $result);
    }

    /**
     * 测试 Promise 等待功能.
     */
    public function testWait()
    {
        $promise = new Promise();

        // 在另一个线程中解析 Promise
        $this->scheduleResolve($promise, 'waited value', 10);

        $result = $promise->wait();
        $this->assertEquals('waited value', $result);
    }

    /**
     * 测试 Promise 等待被拒绝.
     */
    public function testWaitWithRejection()
    {
        $promise = new Promise();
        $exception = new Exception('Wait failed');

        // 在另一个线程中拒绝 Promise
        $this->scheduleReject($promise, $exception, 10);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Wait failed');
        $promise->wait();
    }

    /**
     * 测试重复解析 Promise 不会更改状态.
     */
    public function testDoubleResolve()
    {
        $promise = new Promise();
        $results = [];

        $promise->then(function ($value) use (&$results) {
            $results[] = $value;
        });

        $promise->resolve('first');
        $promise->resolve('second'); // 这不应该有效

        $this->assertCount(1, $results);
        $this->assertEquals('first', $results[0]);
    }

    /**
     * 测试重复拒绝 Promise 不会更改状态.
     */
    public function testDoubleReject()
    {
        $promise = new Promise();
        $errors = [];

        $promise->catch(function ($reason) use (&$errors) {
            $errors[] = $reason->getMessage();
        });

        $promise->reject(new Exception('first error'));
        $promise->reject(new Exception('second error')); // 这不应该有效

        $this->assertCount(1, $errors);
        $this->assertEquals('first error', $errors[0]);
    }

    /**
     * 安排在一段时间后解析 Promise.
     * @param mixed $value
     */
    private function scheduleResolve(Promise $promise, $value, int $ms): void
    {
        // 为了简化测试，我们直接解析
        $promise->resolve($value);
    }

    /**
     * 安排在一段时间后拒绝 Promise.
     */
    private function scheduleReject(Promise $promise, Exception $reason, int $ms): void
    {
        // 为了简化测试，我们直接拒绝
        $promise->reject($reason);
    }
}
