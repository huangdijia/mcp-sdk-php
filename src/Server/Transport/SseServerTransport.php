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

use JsonException;
use ModelContextProtocol\SDK\Exceptions\McpError;
use ModelContextProtocol\SDK\Shared\Transport;
use ModelContextProtocol\SDK\Types;

/**
 * Server transport for SSE: this will send messages over an SSE connection
 * and receive messages from HTTP POST requests.
 */
class SseServerTransport implements Transport
{
    /**
     * Maximum message size in bytes (4MB).
     *
     * @var int
     */
    private const MAXIMUM_MESSAGE_SIZE = 4194304; // 4MB

    /**
     * The SSE response resource.
     *
     * @var resource|null
     */
    private $sseResponse;

    /**
     * Session ID for this transport.
     */
    private string $sessionId;

    /**
     * Callback for when a message is received.
     *
     * @var callable|null
     */
    private $onMessage;

    /**
     * Callback for when the connection is closed.
     *
     * @var callable|null
     */
    private $onClose;

    /**
     * Callback for when an error occurs.
     *
     * @var callable|null
     */
    private $onError;

    /**
     * Creates a new SSE server transport, which will direct the client to POST messages
     * to the URL identified by `$endpoint`.
     *
     * @param string $endpoint the endpoint URL for POST requests
     * @param resource $response the response resource for SSE streaming
     */
    public function __construct(
        private string $endpoint,
        private $response
    ) {
        $this->sessionId = $this->generateUuid();
    }

    /**
     * Start the SSE connection.
     *
     * @throws McpError if already started
     */
    public function start(): void
    {
        if ($this->sseResponse !== null) {
            throw new McpError(
                'SSEServerTransport already started! If using Server class, note that connect() calls start() automatically.',
                Types::ERROR_CODE['InternalError']
            );
        }

        // Set appropriate headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        // Disable output buffering and enable implicit flush
        if (ob_get_level()) {
            ob_end_clean();
        }

        ob_implicit_flush(true);

        // Send the endpoint event
        echo "event: endpoint\n";
        echo 'data: ' . urlencode("{$this->endpoint}?sessionId={$this->sessionId}") . "\n\n";

        $this->sseResponse = $this->response;

        // Register shutdown function to handle connection close
        register_shutdown_function(function () {
            $this->sseResponse = null;
            if ($this->onClose) {
                call_user_func($this->onClose);
            }
        });
    }

    /**
     * Handle incoming POST message.
     *
     * @param string|null $body the raw request body
     * @param string|null $contentType the content type header
     * @return bool whether the message was handled successfully
     * @throws McpError if connection not established
     */
    public function handlePostMessage(?string $body = null, ?string $contentType = null): bool
    {
        if ($this->sseResponse === null) {
            $message = 'SSE connection not established';
            http_response_code(500);
            echo $message;
            throw new McpError($message, Types::ERROR_CODE['InternalError']);
        }

        try {
            // Validate content type
            if (! $contentType || strpos($contentType, 'application/json') === false) {
                throw new McpError("Unsupported content-type: {$contentType}", Types::ERROR_CODE['InvalidRequest']);
            }

            // Check message size
            if ($body !== null && strlen($body) > self::MAXIMUM_MESSAGE_SIZE) {
                throw new McpError(
                    'Message too large: maximum size is ' . (self::MAXIMUM_MESSAGE_SIZE / 1024 / 1024) . 'MB',
                    Types::ERROR_CODE['InvalidRequest']
                );
            }

            return $this->handleMessage($body);
        } catch (McpError $error) {
            http_response_code(400);
            echo $error->getMessage();

            if ($this->onError) {
                call_user_func($this->onError, $error);
            }

            return false;
        }
    }

    /**
     * Handle a client message, regardless of how it arrived.
     *
     * @param string|null $message the raw message
     * @return bool whether the message was successfully processed
     */
    public function handleMessage(?string $message): bool
    {
        if ($message === null) {
            return false;
        }

        try {
            $parsedMessage = json_decode($message, true, 512, JSON_THROW_ON_ERROR);

            if ($this->onMessage) {
                call_user_func($this->onMessage, $parsedMessage);
            }

            http_response_code(202);
            echo 'Accepted';
            return true;
        } catch (JsonException $e) {
            http_response_code(400);
            echo 'Invalid message: ' . $message;

            if ($this->onError) {
                call_user_func($this->onError, $e);
            }

            return false;
        }
    }

    /**
     * Send a message through the transport.
     *
     * @param string $message the message to send
     * @throws McpError if not connected
     */
    public function send(string $message): void
    {
        if ($this->sseResponse === null) {
            throw new McpError('Not connected', Types::ERROR_CODE['InternalError']);
        }

        echo "event: message\n";
        echo "data: {$message}\n\n";

        if (connection_status() !== CONNECTION_NORMAL) {
            $this->close();
        }
    }

    /**
     * Close the transport connection.
     */
    public function close(): void
    {
        if ($this->sseResponse !== null) {
            $this->sseResponse = null;

            if ($this->onClose) {
                call_user_func($this->onClose);
            }
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
     * Get the session ID for this transport.
     *
     * @return string the session ID
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Generate a UUID v4.
     *
     * @return string the generated UUID
     */
    private function generateUuid(): string
    {
        // Generate 16 random bytes
        $data = random_bytes(16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80);

        // Format as string
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
