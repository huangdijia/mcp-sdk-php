<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Server\Transport;

use Exception;
use ModelContextProtocol\SDK\Shared\Transport;

/**
 * Server-Sent Events (SSE) transport implementation for MCP servers.
 */
class SseServerTransport implements Transport
{
    /**
     * @var ?resource the output stream
     */
    private $output;

    /**
     * @var bool whether the transport is active
     */
    private bool $active = false;

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
     * @var array pending messages to be sent when connection becomes active
     */
    private array $pendingMessages = []; // @phpstan-ignore-line

    /**
     * Constructor.
     *
     * @param resource|null $output the output stream (defaults to php://output)
     */
    public function __construct($output = null)
    {
        $this->output = $output ?? fopen('php://output', 'w');
        $this->active = true;

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable buffering for Nginx

        // Flush headers
        flush();
    }

    /**
     * Send a message through the transport.
     *
     * @param string $message the message to send
     */
    public function send(string $message): void
    {
        if (! $this->active) {
            // Store message in pending queue for later sending
            $this->pendingMessages[] = $message;
            return;
        }

        // Format message as SSE
        $formattedMessage = "data: {$message}\n\n";

        // Write to output
        fwrite($this->output, $formattedMessage);
        fflush($this->output);
    }

    /**
     * Close the transport connection.
     */
    public function close(): void
    {
        $this->active = false;

        if ($this->output !== null) {
            fclose($this->output);
            $this->output = null;
        }

        if ($this->onClose) {
            call_user_func($this->onClose);
        }
    }

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
     * Set callback for when the connection is closed.
     *
     * @param callable $callback the callback function
     */
    public function setOnClose(callable $callback): void
    {
        $this->onClose = $callback;
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
     * Handle an incoming message.
     *
     * @param string $message the message
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
     * @param Exception $error the error
     */
    public function handleError(Exception $error): void
    {
        if ($this->onError) {
            call_user_func($this->onError, $error);
        }
    }
}
