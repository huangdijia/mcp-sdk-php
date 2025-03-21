<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Your name <your-mail@gmail.com>
 */

namespace ModelContextProtocol\SDK\Shared;

/**
 * Interface for transport layers used by the protocol.
 */
interface Transport
{
    /**
     * Send a message through the transport.
     *
     * @param string $message the message to send
     */
    public function send(string $message): void;

    /**
     * Close the transport connection.
     */
    public function close(): void;

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
