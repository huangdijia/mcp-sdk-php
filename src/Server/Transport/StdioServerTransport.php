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

use ModelContextProtocol\SDK\Shared\Transport;

/**
 * STDIO transport implementation for MCP servers.
 */
class StdioServerTransport implements Transport
{
    use Traits\InteractsWithCallbacks;

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
        $this->useInternalLoop = $useInternalLoop;
    }

    public function start(): void
    {
        $this->active = true;

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
    public function writeMessage(string $message): void
    {
        if ($this->active) {
            fwrite($this->output, $message . "\n");
            fflush($this->output);
        }
    }

    /**
     * Close the transport connection.
     */
    public function close(): void
    {
        $this->active = false;

        $this->handleClose();
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
