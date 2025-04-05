<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Shared;

use Throwable;

/**
 * A simple Promise implementation for handling asynchronous operations.
 */
class Promise
{
    /**
     * @var mixed the resolved value
     */
    private $value;

    /**
     * @var Throwable|null the rejection reason
     */
    private $reason;

    /**
     * @var string the promise state: 'pending', 'fulfilled', or 'rejected'
     */
    private string $state = 'pending';

    /**
     * @var array<callable> callbacks to be called when the promise is resolved
     */
    private array $onFulfilledCallbacks = [];

    /**
     * @var array<callable> callbacks to be called when the promise is rejected
     */
    private array $onRejectedCallbacks = [];

    /**
     * Resolve the promise with a value.
     *
     * @param mixed $value the value to resolve with
     */
    public function resolve($value): void
    {
        if ($this->state !== 'pending') {
            return;
        }

        $this->state = 'fulfilled';
        $this->value = $value;

        foreach ($this->onFulfilledCallbacks as $callback) {
            $callback($value);
        }

        $this->onFulfilledCallbacks = [];
        $this->onRejectedCallbacks = [];
    }

    /**
     * Reject the promise with a reason.
     *
     * @param Throwable $reason the reason for rejection
     */
    public function reject(Throwable $reason): void
    {
        if ($this->state !== 'pending') {
            return;
        }

        $this->state = 'rejected';
        $this->reason = $reason;

        foreach ($this->onRejectedCallbacks as $callback) {
            $callback($reason);
        }

        $this->onFulfilledCallbacks = [];
        $this->onRejectedCallbacks = [];
    }

    /**
     * Add callbacks to be called when the promise is resolved or rejected.
     *
     * @param callable|null $onFulfilled called when the promise is resolved
     * @param callable|null $onRejected called when the promise is rejected
     * @return Promise a new promise
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): Promise
    {
        $promise = new Promise();

        $onFulfilled = $onFulfilled ?? function ($value) {
            return $value;
        };

        $onRejected = $onRejected ?? function ($reason) {
            throw $reason;
        };

        $resolvePromise = function ($value) use ($promise, $onFulfilled) {
            try {
                $result = $onFulfilled($value);
                $promise->resolve($result);
            } catch (Throwable $e) {
                $promise->reject($e);
            }
        };

        $rejectPromise = function ($reason) use ($promise, $onRejected) {
            try {
                $result = $onRejected($reason);
                $promise->resolve($result);
            } catch (Throwable $e) {
                $promise->reject($e);
            }
        };

        if ($this->state === 'fulfilled') {
            $resolvePromise($this->value);
        } elseif ($this->state === 'rejected') {
            $rejectPromise($this->reason);
        } else {
            $this->onFulfilledCallbacks[] = $resolvePromise;
            $this->onRejectedCallbacks[] = $rejectPromise;
        }

        return $promise;
    }

    /**
     * Add a callback to be called when the promise is rejected.
     *
     * @param callable $onRejected called when the promise is rejected
     * @return Promise a new promise
     */
    public function catch(callable $onRejected): Promise
    {
        return $this->then(null, $onRejected);
    }

    /**
     * Wait for the promise to be resolved or rejected.
     *
     * @return mixed the resolved value
     * @throws Throwable if the promise is rejected
     */
    public function wait()
    {
        // Simple blocking wait implementation
        while ($this->state === 'pending') {
            usleep(1000); // Sleep for 1ms
        }

        if ($this->state === 'rejected') {
            throw $this->reason;
        }

        return $this->value;
    }
}
