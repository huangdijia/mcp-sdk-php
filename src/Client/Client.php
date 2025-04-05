<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Client;

use ModelContextProtocol\SDK\Exceptions\McpError;
use ModelContextProtocol\SDK\Shared\Protocol;
use ModelContextProtocol\SDK\Shared\Transport;
use ModelContextProtocol\SDK\Types;

/**
 * An MCP client on top of a pluggable transport.
 *
 * The client will automatically begin the initialization flow with the server when connect() is called.
 */
class Client extends Protocol
{
    /**
     * @var array|null server capabilities
     */
    private ?array $serverCapabilities = null;

    /**
     * @var array|null server version information
     */
    private ?array $serverVersion = null;

    /**
     * @var array client capabilities
     */
    private array $capabilities;

    /**
     * @var string|null server instructions
     */
    private ?string $instructions = null;

    /**
     * Initializes this client with the given name and version information.
     *
     * @param array $clientInfo client name and version information
     * @param array $options client options
     */
    public function __construct(private array $clientInfo, array $options = [])
    {
        parent::__construct($options);
        $this->capabilities = $options['capabilities'] ?? [];
    }

    /**
     * Connect to a server using the given transport.
     *
     * This will automatically begin the initialization flow with the server.
     *
     * @param Transport $transport the transport to connect to
     * @throws McpError if initialization fails
     */
    public function connect(Transport $transport): void
    {
        parent::connect($transport);

        // Initialize the server
        $result = $this->request('initialize', [
            'protocolVersion' => Types::LATEST_PROTOCOL_VERSION,
            'clientInfo' => $this->clientInfo,
            'capabilities' => $this->capabilities,
        ]);

        // Store server information
        $this->serverVersion = $result['serverInfo'] ?? null;
        $this->serverCapabilities = $result['capabilities'] ?? [];
        $this->instructions = $result['instructions'] ?? null;

        // Send initialized notification
        $this->notify('initialized', []);
        $this->initialized = true;
    }

    /**
     * Get the server capabilities.
     *
     * @return array|null the server capabilities
     */
    public function getServerCapabilities(): ?array
    {
        return $this->serverCapabilities;
    }

    /**
     * Get the server version information.
     *
     * @return array|null the server version information
     */
    public function getServerVersion(): ?array
    {
        return $this->serverVersion;
    }

    /**
     * Get the server instructions.
     *
     * @return string|null the server instructions
     */
    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    /**
     * Check if the server supports a specific capability.
     *
     * @param string $capability the capability to check
     * @return bool whether the capability is supported
     */
    public function serverSupports(string $capability): bool
    {
        if (! $this->serverCapabilities) {
            return false;
        }

        return isset($this->serverCapabilities[$capability]) && $this->serverCapabilities[$capability] === true;
    }

    /**
     * Call a tool on the server.
     *
     * @param string $name the tool name
     * @param array $params the tool parameters
     * @param array $options request options
     * @return array the tool result
     * @throws McpError if the tool call fails
     */
    public function callTool(string $name, array $params = [], array $options = []): array
    {
        return $this->request('callTool', [
            'name' => $name,
            'params' => $params,
        ], $options);
    }

    /**
     * List available tools on the server.
     *
     * @param array $options request options
     * @return array the list of tools
     * @throws McpError if the request fails
     */
    public function listTools(array $options = []): array
    {
        return $this->request('listTools', [], $options);
    }

    /**
     * List available resources on the server.
     *
     * @param array $options request options
     * @return array the list of resources
     * @throws McpError if the request fails
     */
    public function listResources(array $options = []): array
    {
        return $this->request('listResources', [], $options);
    }

    /**
     * Read a resource from the server.
     *
     * @param string $uri the resource URI
     * @param array $options request options
     * @return array the resource content
     * @throws McpError if the request fails
     */
    public function readResource(string $uri, array $options = []): array
    {
        return $this->request('readResource', [
            'uri' => $uri,
        ], $options);
    }
}
