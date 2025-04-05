<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Your name <your-mail@gmail.com>
 */

namespace ModelContextProtocol\SDK\Client\Transport;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use ModelContextProtocol\SDK\Shared\Transport;

/**
 * Server-Sent Events (SSE) transport implementation for MCP clients.
 *
 * This transport connects to an SSE endpoint and processes events in a non-blocking manner.
 * To use this transport, you need to periodically call the processAvailableData() method
 * to process any incoming events, typically in a loop.
 *
 * Example usage:
 * ```php
 * $transport = new SseClientTransport('http://example.com/sse');
 * $transport->onmessage = function($data) { echo $data; };
 *
 * while (true) {
 *     $transport->processAvailableData();
 *     usleep(100000); // Sleep 100ms to avoid high CPU usage
 * }
 * ```
 */
class SseClientTransport implements Transport
{
    /**
     * @var callable|null callback for when a message is received
     */
    public $onMessage;

    /**
     * @var callable|null callback for when the connection is closed
     */
    public $onClose;

    /**
     * @var callable|null callback for when an error occurs
     */
    public $onError;

    /**
     * @var HttpClient the HTTP client
     */
    private HttpClient $client;

    /**
     * @var string the server URL
     */
    private string $url;

    /**
     * @var resource|null the SSE stream
     */
    private $stream;

    /**
     * @var bool whether the transport is active
     */
    private bool $active = false;

    /**
     * Constructor.
     *
     * @param string $url the server URL
     * @param array $options HTTP client options
     */
    public function __construct(string $url, array $options = [])
    {
        $this->url = $url;
        $this->client = new HttpClient($options);
        $this->active = true;

        // Start SSE connection
        try {
            $this->connect();
        } catch (RequestException $e) {
            if ($this->onError) {
                call_user_func($this->onError, $e);
            }
        }
    }

    /**
     * Send a message through the transport.
     *
     * @param string $message the message to send
     */
    public function send(string $message): void
    {
        if (! $this->active) {
            return;
        }

        try {
            $this->client->post($this->url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $message,
            ]);
        } catch (RequestException $e) {
            if ($this->onError) {
                call_user_func($this->onError, $e);
            }
        }
    }

    /**
     * Close the transport connection.
     */
    public function close(): void
    {
        $this->active = false;

        if ($this->stream) {
            fclose($this->stream);
            $this->stream = null;
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
     * Process available data from the SSE stream.
     * This method should be called periodically to process incoming events.
     */
    public function processAvailableData(): void
    {
        if (! $this->stream || ! $this->active) {
            return;
        }

        static $buffer = '';

        // Read available data from the stream
        while ($this->active && $this->stream && ! feof($this->stream)) {
            $line = fgets($this->stream);
            if ($line === false) {
                // No more data available at the moment
                break;
            }

            $buffer .= $line;

            // Check if we have a complete event (double newline)
            if (strpos($buffer, "\n\n") !== false) {
                $events = explode("\n\n", $buffer);
                $buffer = array_pop($events); // Keep the last incomplete event

                foreach ($events as $event) {
                    $this->processEvent($event);
                }
            }
        }
    }

    /**
     * Connect to the SSE stream.
     */
    private function connect(): void
    {
        try {
            $response = $this->client->get($this->url, [
                'headers' => [
                    'Accept' => 'text/event-stream',
                ],
                'stream' => true,
            ]);

            $this->stream = $response->getBody()->detach();
            if ($this->stream) {
                // Set stream to non-blocking mode
                stream_set_blocking($this->stream, false);

                // Start reading from the stream
                $this->startReading();
            }
        } catch (RequestException $e) {
            if ($this->onError) {
                call_user_func($this->onError, $e);
            }
        }
    }

    /**
     * Start reading from the SSE stream.
     */
    private function startReading(): void
    {
        if (! $this->stream) {
            return;
        }

        // Process any available data immediately
        $this->processAvailableData();

        // In a real-world application, you would set up a timer or use an event loop
        // to periodically call processAvailableData() without blocking
        // For example, using React PHP or Amp for async I/O

        // For simplicity in this implementation, we'll rely on the user code to
        // periodically call a public method to process data
    }

    /**
     * Process an SSE event.
     *
     * @param string $event the event data
     */
    private function processEvent(string $event): void
    {
        // Skip empty events
        if (trim($event) === '') {
            return;
        }

        // Parse the event
        $data = '';
        $eventType = 'message';
        $id = null;

        // Split the event into lines
        $lines = explode("\n", $event);

        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (trim($line) === '' || strpos($line, ':') === 0) {
                continue;
            }

            // Parse field:value format
            if (strpos($line, ':') !== false) {
                [$field, $value] = explode(':', $line, 2);
                $value = ltrim($value); // Remove leading space if present

                switch ($field) {
                    case 'data':
                        $data .= $value . "\n";
                        break;
                    case 'event':
                        $eventType = $value;
                        break;
                    case 'id':
                        $id = $value;
                        break;
                    case 'retry':
                        // Reconnection time handling could be implemented here
                        break;
                }
            }
        }

        // Trim the trailing newline from data
        $data = rtrim($data);

        // Trigger the onmessage callback if set
        if ($this->onMessage && ! empty($data)) {
            call_user_func($this->onMessage, $data);
        }
    }
}
