<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Your name <your-mail@gmail.com>
 */

namespace ModelContextProtocol\SDK\Server;

use ModelContextProtocol\SDK\Exceptions\McpError;
use ModelContextProtocol\SDK\Shared\Protocol;
use ModelContextProtocol\SDK\Shared\Transport;
use ModelContextProtocol\SDK\Types;

/**
 * An MCP server on top of a pluggable transport.
 *
 * This server will automatically respond to the initialization flow as initiated from the client.
 */
class Server extends Protocol
{
    /**
     * @var callable|null callback for when initialization has fully completed
     */
    public $onInitialized;

    /**
     * @var array|null client capabilities
     */
    private ?array $clientCapabilities = null;

    /**
     * @var array|null client version information
     */
    private ?array $clientVersion = null;

    /**
     * @var array server capabilities
     */
    private array $capabilities;

    /**
     * @var string|null server instructions
     */
    private ?string $instructions = null;

    /**
     * Initializes this server with the given name and version information.
     *
     * @param array $serverInfo server name and version information
     * @param array $options server options
     */
    public function __construct(private array $serverInfo, array $options = [])
    {
        parent::__construct($options);
        $this->capabilities = $options['capabilities'] ?? [];
        $this->instructions = $options['instructions'] ?? null;

        // Set up initialization handlers
        $this->setRequestHandler('initialize', [$this, 'handleInitialize']);
        $this->setNotificationHandler('initialized', [$this, 'handleInitialized']);
    }

    /**
     * Handle the initialize request from a client.
     *
     * @param array $params the initialization parameters
     * @return array the initialization result
     * @throws McpError if initialization fails
     */
    public function handleInitialize(array $params): array
    {
        if ($this->initialized) {
            throw new McpError('Server already initialized', Types::ERROR_CODE['InvalidRequest']);
        }

        // Validate protocol version
        $protocolVersion = $params['protocolVersion'] ?? null;
        if (! $protocolVersion || ! in_array($protocolVersion, Types::SUPPORTED_PROTOCOL_VERSIONS)) {
            throw new McpError(
                'Unsupported protocol version: ' . $protocolVersion,
                Types::ERROR_CODE['InvalidRequest']
            );
        }

        // Store client information
        $this->clientVersion = $params['clientInfo'] ?? null;
        $this->clientCapabilities = $params['capabilities'] ?? [];

        // Return server information
        $result = [
            'serverInfo' => $this->serverInfo,
            'capabilities' => $this->capabilities,
        ];

        if ($this->instructions !== null) {
            $result['instructions'] = $this->instructions;
        }

        return $result;
    }

    /**
     * Handle the initialized notification from a client.
     *
     * @param array $params the notification parameters
     */
    public function handleInitialized(array $params): void
    {
        $this->initialized = true;

        if ($this->onInitialized) {
            call_user_func($this->onInitialized);
        }
    }

    /**
     * Get the client capabilities.
     *
     * @return array|null the client capabilities
     */
    public function getClientCapabilities(): ?array
    {
        return $this->clientCapabilities;
    }

    /**
     * Get the client version information.
     *
     * @return array|null the client version information
     */
    public function getClientVersion(): ?array
    {
        return $this->clientVersion;
    }

    /**
     * Check if the client supports a specific capability.
     *
     * @param string $capability the capability to check
     * @return bool whether the capability is supported
     */
    public function clientSupports(string $capability): bool
    {
        if (! $this->clientCapabilities) {
            return false;
        }

        return isset($this->clientCapabilities[$capability]) && $this->clientCapabilities[$capability] === true;
    }
}
