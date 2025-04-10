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

/**
 * Interface for transport layers used by the protocol.
 */
interface Transport
{
    /**
     * Start the transport connection.
     */
    public function start(): void;

    /**
     * Handle incoming messages from the transport.
     *
     * @param string $message the message to handle
     */
    public function handleMessage(string $message): void;

    /**
     * Send a message through the transport.
     *
     * @param string $message the message to send
     */
    public function writeMessage(string $message): void;

    /**
     * Close the transport connection.
     */
    public function stop(): void;

    /**
     * Set callback for when a message is received.
     *
     * @param callable $callback the callback function
     */
    public function setOnMessage(callable $callback): void;

    /**
     * Set callback for when the connection is closed.
     *
     * @param callable $callback the callback function
     */
    public function setOnClose(callable $callback): void;

    /**
     * Set callback for when an error occurs.
     *
     * @param callable $callback the callback function
     */
    public function setOnError(callable $callback): void;
}
