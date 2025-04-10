<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Server\Transport\Traits;

use Throwable;

trait InteractsWithCallbacks
{
    /**
     * @var callable|null callback for when a message is received
     */
    private $onMessage;

    /**
     * @var callable|null callback for when the connection is closed
     */
    private $onClose;

    /**
     * @var callable|null callback for when an error occurs
     */
    private $onError;

    /**
     * Set callback for when a message is received.
     *
     * @param callable $callback the callback function
     */
    public function setOnMessage(callable $callback): void
    {
        $this->onMessage = $callback;
    }

    /**
     * Set callback for when an error occurs.
     *
     * @param callable $callback the callback function
     */
    public function setOnError(callable $callback): void
    {
        $this->onError = $callback;
    }

    /**
     * Set callback for when the connection is closed.
     *
     * @param callable $callback the callback function
     */
    public function setOnClose(callable $callback): void
    {
        $this->onClose = $callback;
    }

    /**
     * Handle an incoming message manually.
     *
     * @param string $message the message to handle
     */
    public function handleMessage(string $message): void
    {
        if ($this->onMessage) {
            call_user_func($this->onMessage, $message);
        }
    }

    /**
     * Handle an error.
     *
     * @param Throwable $error the error
     */
    public function handleError(Throwable $error): void
    {
        if ($this->onError) {
            call_user_func($this->onError, $error);
        }
    }

    public function handleClose(): void
    {
        if ($this->onClose) {
            call_user_func($this->onClose);
        }
    }
}
