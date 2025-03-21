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

use ModelContextProtocol\SDK\Exceptions\McpError;
use ModelContextProtocol\SDK\Exceptions\RequestCancelledError;
use ModelContextProtocol\SDK\Types;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Default request timeout in milliseconds.
 */
const DEFAULT_REQUEST_TIMEOUT_MSEC = 60000;

/**
 * Base class for both client and server implementations of the MCP protocol.
 */
abstract class Protocol
{
    /**
     * @var Transport the transport layer used for communication
     */
    protected ?Transport $transport = null;

    /**
     * @var LoggerInterface the logger instance
     */
    protected LoggerInterface $logger;

    /**
     * @var array<string, callable> request handlers registered by method name
     */
    protected array $requestHandlers = [];

    /**
     * @var array<string, callable> notification handlers registered by method name
     */
    protected array $notificationHandlers = [];

    /**
     * @var array<string, array> pending requests by ID
     */
    protected array $pendingRequests = [];

    /**
     * @var bool whether to enforce strict capabilities checking
     */
    protected bool $enforceStrictCapabilities;

    /**
     * @var bool whether the protocol is initialized
     */
    protected bool $initialized = false;

    /**
     * Constructor.
     *
     * @param array $options protocol options
     */
    public function __construct(array $options = [])
    {
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->enforceStrictCapabilities = $options['enforceStrictCapabilities'] ?? false;
    }

    /**
     * Connect to the transport layer.
     *
     * @param Transport $transport the transport to connect to
     */
    public function connect(Transport $transport): void
    {
        $this->transport = $transport;
        $this->transport->setOnMessage([$this, 'handleMessage']);
        $this->transport->setOnClose([$this, 'handleClose']);
        $this->transport->setOnError([$this, 'handleError']);
    }

    /**
     * Disconnect from the transport layer.
     */
    public function disconnect(): void
    {
        if ($this->transport) {
            $this->transport->close();
            $this->transport = null;
        }
    }

    /**
     * Set a request handler for a specific method.
     *
     * @param string $method the method name
     * @param callable $handler the handler function
     */
    public function setRequestHandler(string $method, callable $handler): void
    {
        $this->requestHandlers[$method] = $handler;
    }

    /**
     * Set a notification handler for a specific method.
     *
     * @param string $method the method name
     * @param callable $handler the handler function
     */
    public function setNotificationHandler(string $method, callable $handler): void
    {
        $this->notificationHandlers[$method] = $handler;
    }

    /**
     * Send a request to the remote endpoint.
     *
     * @param string $method the method name
     * @param array $params the parameters
     * @param array $options request options
     * @return array the response result
     * @throws McpError if the request fails
     */
    public function request(string $method, array $params = [], array $options = []): array
    {
        if (! $this->transport) {
            throw new McpError('Transport not connected', Types::ERROR_CODE['InternalError']);
        }

        $id = uniqid('req_', true);
        $timeout = $options['timeout'] ?? DEFAULT_REQUEST_TIMEOUT_MSEC;
        $resetTimeoutOnProgress = $options['resetTimeoutOnProgress'] ?? false;
        $maxTotalTimeout = $options['maxTotalTimeout'] ?? null;

        // Add progress token if onprogress callback is provided
        if (isset($options['onprogress'])) {
            $params['_meta'] = $params['_meta'] ?? [];
            $params['_meta']['progressToken'] = $id;
        }

        $request = [
            'jsonrpc' => Types::JSONRPC_VERSION,
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ];

        $promise = new Promise();
        $this->pendingRequests[$id] = [
            'promise' => $promise,
            'onprogress' => $options['onprogress'] ?? null,
            'timeout' => $timeout,
            'resetTimeoutOnProgress' => $resetTimeoutOnProgress,
            'maxTotalTimeout' => $maxTotalTimeout,
            'startTime' => microtime(true),
            'timeoutId' => null,
        ];

        // Set up timeout
        $timeoutId = null;
        if ($timeout > 0) {
            $timeoutId = $this->setupTimeout($id, $timeout);
            $this->pendingRequests[$id]['timeoutId'] = $timeoutId;
        }

        // Handle cancellation
        if (isset($options['signal']) && $options['signal'] instanceof AbortSignal) {
            $options['signal']->onabort = function () use ($id) {
                $this->cancelRequest($id);
            };
        }

        $this->transport->send(json_encode($request));

        try {
            return $promise->wait();
        } catch (Throwable $e) {
            throw $e;
        } finally {
            unset($this->pendingRequests[$id]);
        }
    }

    /**
     * Send a notification to the remote endpoint.
     *
     * @param string $method the method name
     * @param array $params the parameters
     * @throws McpError if the notification fails
     */
    public function notify(string $method, array $params = []): void
    {
        if (! $this->transport) {
            throw new McpError('Transport not connected', Types::ERROR_CODE['InternalError']);
        }

        $notification = [
            'jsonrpc' => Types::JSONRPC_VERSION,
            'method' => $method,
            'params' => $params,
        ];

        $this->transport->send(json_encode($notification));
    }

    /**
     * Handle an incoming message from the transport.
     *
     * @param string $message the raw message
     */
    public function handleMessage(string $message): void
    {
        try {
            $data = json_decode($message, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to parse JSON message', [
                    'error' => json_last_error_msg(),
                    'message' => $message,
                ]);
                return;
            }

            // Handle response
            if (isset($data['id'], $data['result'])) {
                $this->handleResponse($data);
                return;
            }

            // Handle error response
            if (isset($data['id'], $data['error'])) {
                $this->handleErrorResponse($data);
                return;
            }

            // Handle request
            if (isset($data['id'], $data['method'])) {
                $this->handleRequest($data);
                return;
            }

            // Handle notification
            if (isset($data['method']) && ! isset($data['id'])) {
                $this->handleNotification($data);
                return;
            }

            $this->logger->warning('Received unknown message format', ['message' => $data]);
        } catch (Throwable $e) {
            $this->logger->error('Error handling message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle transport close event.
     */
    public function handleClose(): void
    {
        $this->logger->info('Transport closed');

        // Reject all pending requests
        foreach ($this->pendingRequests as $id => $pendingRequest) {
            $promise = $pendingRequest['promise'];
            $exception = new McpError('Transport closed', Types::ERROR_CODE['RequestFailed']);
            $promise->reject($exception);
        }

        $this->pendingRequests = [];
    }

    /**
     * Handle transport error event.
     *
     * @param Throwable $error the error
     */
    public function handleError(Throwable $error): void
    {
        $this->logger->error('Transport error', [
            'error' => $error->getMessage(),
            'trace' => $error->getTraceAsString(),
        ]);

        // Reject all pending requests
        foreach ($this->pendingRequests as $id => $pendingRequest) {
            $promise = $pendingRequest['promise'];
            $exception = new McpError('Transport error: ' . $error->getMessage(), Types::ERROR_CODE['RequestFailed']);
            $promise->reject($exception);
        }

        $this->pendingRequests = [];
    }

    /**
     * Check if a request has timed out.
     *
     * @param string $id The request ID.
     * @return bool Whether the request has timed out.
     */
    protected function hasTimedOut(string $id): bool
    {
        if (!isset($this->pendingRequests[$id])) {
            return false;
        }

        $pendingRequest = $this->pendingRequests[$id];
        $startTime = $pendingRequest['startTime'];
        $timeout = $pendingRequest['timeout'];
        $maxTotalTimeout = $pendingRequest['maxTotalTimeout'] ?? null;

        // Check if the request has exceeded its timeout
        $elapsedTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Check against max total timeout if set
        if ($maxTotalTimeout !== null && $elapsedTime > $maxTotalTimeout) {
            return true;
        }

        // Check against regular timeout
        return $elapsedTime > $timeout;
    }

    /**
     * Handle a response message.
     *
     * @param array $data the response data
     */
    protected function handleResponse(array $data): void
    {
        $id = $data['id'];
        if (! isset($this->pendingRequests[$id])) {
            $this->logger->warning('Received response for unknown request', ['id' => $id]);
            return;
        }

        $pendingRequest = $this->pendingRequests[$id];
        $promise = $pendingRequest['promise'];

        // Clear timeout if set
        if ($pendingRequest['timeoutId']) {
            $this->clearTimeout($pendingRequest['timeoutId']);
        }

        $promise->resolve($data['result']);
    }

    /**
     * Handle an error response message.
     *
     * @param array $data the error response data
     */
    protected function handleErrorResponse(array $data): void
    {
        $id = $data['id'];
        if (! isset($this->pendingRequests[$id])) {
            $this->logger->warning('Received error for unknown request', ['id' => $id]);
            return;
        }

        $pendingRequest = $this->pendingRequests[$id];
        $promise = $pendingRequest['promise'];

        // Clear timeout if set
        if ($pendingRequest['timeoutId']) {
            $this->clearTimeout($pendingRequest['timeoutId']);
        }

        $error = $data['error'];
        $errorMessage = $error['message'] ?? 'Unknown error';
        $errorCode = $error['code'] ?? Types::ERROR_CODE['UnknownErrorCode'];
        $errorData = $error['data'] ?? null;

        $exception = new McpError($errorMessage, $errorCode, $errorData);
        $promise->reject($exception);
    }

    /**
     * Handle a request message.
     *
     * @param array $data the request data
     */
    protected function handleRequest(array $data): void
    {
        $id = $data['id'];
        $method = $data['method'];
        $params = $data['params'] ?? [];

        if (! isset($this->requestHandlers[$method])) {
            $this->sendErrorResponse($id, 'Method not found', Types::ERROR_CODE['MethodNotFound']);
            return;
        }

        try {
            $handler = $this->requestHandlers[$method];
            $result = $handler($params);

            $this->sendResponse($id, $result);
        } catch (Throwable $e) {
            $errorCode = $e instanceof McpError ? $e->getCode() : Types::ERROR_CODE['InternalError'];
            $this->sendErrorResponse($id, $e->getMessage(), $errorCode);
        }
    }

    /**
     * Handle a notification message.
     *
     * @param array $data the notification data
     */
    protected function handleNotification(array $data): void
    {
        $method = $data['method'];
        $params = $data['params'] ?? [];

        // Handle progress notifications
        if ($method === 'progress' && isset($params['token'])) {
            $this->handleProgressNotification($params);
            return;
        }

        if (! isset($this->notificationHandlers[$method])) {
            $this->logger->debug('No handler for notification', ['method' => $method]);
            return;
        }

        try {
            $handler = $this->notificationHandlers[$method];
            $handler($params);
        } catch (Throwable $e) {
            $this->logger->error('Error handling notification', [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle a progress notification.
     *
     * @param array $params the progress notification parameters
     */
    protected function handleProgressNotification(array $params): void
    {
        $token = $params['token'];
        if (! isset($this->pendingRequests[$token])) {
            $this->logger->debug('Received progress for unknown request', ['token' => $token]);
            return;
        }

        $pendingRequest = $this->pendingRequests[$token];
        if ($pendingRequest['onprogress']) {
            $pendingRequest['onprogress']($params);
        }

        // Reset timeout if configured
        if ($pendingRequest['resetTimeoutOnProgress'] && $pendingRequest['timeoutId']) {
            $this->clearTimeout($pendingRequest['timeoutId']);
            $timeoutId = $this->setupTimeout($token, $pendingRequest['timeout']);
            $this->pendingRequests[$token]['timeoutId'] = $timeoutId;
        }
    }

    /**
     * Send a response to a request.
     *
     * @param string $id the request ID
     * @param array $result the result data
     */
    protected function sendResponse(string $id, array $result): void
    {
        if (! $this->transport) {
            $this->logger->error('Cannot send response: transport not connected');
            return;
        }

        $response = [
            'jsonrpc' => Types::JSONRPC_VERSION,
            'id' => $id,
            'result' => $result,
        ];

        $this->transport->send(json_encode($response));
    }

    /**
     * Send an error response to a request.
     *
     * @param string $id the request ID
     * @param string $message the error message
     * @param int $code the error code
     * @param mixed $data additional error data
     */
    protected function sendErrorResponse(string $id, string $message, int $code, $data = null): void
    {
        if (! $this->transport) {
            $this->logger->error('Cannot send error response: transport not connected');
            return;
        }

        $response = [
            'jsonrpc' => Types::JSONRPC_VERSION,
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($data !== null) {
            $response['error']['data'] = $data;
        }

        $this->transport->send(json_encode($response));
    }

    /**
     * Set up a timeout for a request.
     *
     * @param string $id the request ID
     * @param int $timeout the timeout in milliseconds
     * @return string the timeout ID
     */
    protected function setupTimeout(string $id, int $timeout): string
    {
        return uniqid('timeout_', true);
        // In a real implementation, we would use a timer here
        // For PHP, we could use pcntl_alarm or a similar mechanism
        // For now, we'll just simulate it with a simple check in the wait method
    }

    /**
     * Clear a timeout.
     *
     * @param string $timeoutId the timeout ID
     */
    protected function clearTimeout(string $timeoutId): void
    {
        // In a real implementation, we would cancel a timer here
        // For PHP, we could use pcntl_alarm or a similar mechanism
    }

    /**
     * Cancel a pending request.
     *
     * @param string $id the request ID
     */
    protected function cancelRequest(string $id): void
    {
        if (! isset($this->pendingRequests[$id])) {
            return;
        }

        $pendingRequest = $this->pendingRequests[$id];
        $promise = $pendingRequest['promise'];

        // Clear timeout if set
        if ($pendingRequest['timeoutId']) {
            $this->clearTimeout($pendingRequest['timeoutId']);
        }

        // Send cancellation notification
        if ($this->transport) {
            $this->notify('$/cancelRequest', ['id' => $id]);
        }

        $exception = new RequestCancelledError('Request cancelled');
        $promise->reject($exception);
    }
}
