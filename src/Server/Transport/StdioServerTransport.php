<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Your name <your-mail@gmail.com>
 */

namespace ModelContextProtocol\SDK\Server\Transport;

use ModelContextProtocol\SDK\Shared\Transport;
use Throwable;

/**
 * STDIO transport implementation for MCP servers.
 */
class StdioServerTransport implements Transport
{
    /**
     * @var resource the input stream
     */
    private $input;

    /**
     * @var resource the output stream
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
     * @var bool whether the internal reading loop is enabled
     */
    private bool $useInternalLoop = true;

    /**
     * Constructor.
     *
     * @param resource|null $input the input stream (defaults to STDIN)
     * @param resource|null $output the output stream (defaults to STDOUT)
     * @param bool $useInternalLoop whether to use the internal reading loop (defaults to true)
     */
    public function __construct($input = null, $output = null, bool $useInternalLoop = true)
    {
        $this->input = $input ?? STDIN;
        $this->output = $output ?? STDOUT;
        $this->active = true;
        $this->useInternalLoop = $useInternalLoop;

        // Start reading from stdin if internal loop is enabled
        if ($this->useInternalLoop) {
            $this->startReading();
        }
    }

    /**
     * Send a message through the transport.
     *
     * @param string $message the message to send
     */
    public function send(string $message): void
    {
        fwrite($this->output, $message . "\n");
        fflush($this->output);
    }

    /**
     * Close the transport connection.
     */
    public function close(): void
    {
        $this->active = false;

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
     * Start reading from the input stream.
     */
    private function startReading(): void
    {
        // Set stdin to non-blocking mode
        stream_set_blocking($this->input, false);

        // Start a loop to read from stdin
        while ($this->active) {
            $line = fgets($this->input);
            if ($line !== false) {
                $line = trim($line);
                if ($line && $this->onMessage) {
                    call_user_func($this->onMessage, $line);
                }
            }

            // Sleep a bit to avoid high CPU usage
            usleep(10000); // 10ms
        }
    }
}
