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
use ModelContextProtocol\SDK\Shared\ResourceTemplate;
use ModelContextProtocol\SDK\Types;
use Throwable;

/**
 * A high-level MCP server that provides a simpler API for creating MCP servers.
 */
class McpServer extends Server
{
    /**
     * @var array<string, callable> tool handlers by name
     */
    private array $toolHandlers = [];

    /**
     * @var array<string, array> tool schemas by name
     */
    private array $toolSchemas = [];

    /**
     * @var array<string, array> resource handlers by scheme
     */
    private array $resourceHandlers = [];

    /**
     * @var array<string, ResourceTemplate> resource templates by scheme
     */
    private array $resourceTemplates = [];

    /**
     * @var array<string, array> prompt handlers by name
     */
    private array $promptHandlers = [];

    /**
     * @var array<string, array> prompt schemas by name
     */
    private array $promptSchemas = [];

    /**
     * Constructor.
     *
     * @param array $serverInfo server name and version information
     * @param array $options server options
     */
    public function __construct(array $serverInfo, array $options = [])
    {
        parent::__construct($serverInfo, $options);

        // Set up handlers for core MCP methods
        $this->setRequestHandler('callTool', [$this, 'handleCallTool']);
        $this->setRequestHandler('listTools', [$this, 'handleListTools']);
        $this->setRequestHandler('listResources', [$this, 'handleListResources']);
        $this->setRequestHandler('readResource', [$this, 'handleReadResource']);
        $this->setRequestHandler('listPrompts', [$this, 'handleListPrompts']);
        $this->setRequestHandler('getPrompt', [$this, 'handleGetPrompt']);
    }

    /**
     * Register a tool with the server.
     *
     * @param string $name the tool name
     * @param callable $handler the tool handler function
     * @param array $schema optional JSON schema for the tool parameters
     */
    public function tool(string $name, callable $handler, array $schema = []): self
    {
        $this->toolHandlers[$name] = $handler;
        $this->toolSchemas[$name] = $schema;

        return $this;
    }

    /**
     * Register a resource handler with the server.
     *
     * @param string $scheme the resource scheme
     * @param ResourceTemplate $template the resource template
     * @param callable $handler the resource handler function
     */
    public function resource(string $scheme, ResourceTemplate $template, callable $handler): self
    {
        $this->resourceHandlers[$scheme] = $handler;
        $this->resourceTemplates[$scheme] = $template;

        return $this;
    }

    /**
     * Register a prompt with the server.
     *
     * @param string $name the prompt name
     * @param array $prompt the prompt definition
     * @param array $schema optional JSON schema for the prompt parameters
     */
    public function prompt(string $name, array $prompt, array $schema = []): self
    {
        $this->promptHandlers[$name] = $prompt;
        $this->promptSchemas[$name] = $schema;

        return $this;
    }

    /**
     * Handle a callTool request.
     *
     * @param array $params the request parameters
     * @return array the tool result
     * @throws McpError if the tool call fails
     */
    public function handleCallTool(array $params): array
    {
        if (! $this->initialized) {
            throw new McpError('Server not initialized', Types::ERROR_CODE['ServerNotInitialized']);
        }

        $name = $params['name'] ?? null;
        if (! $name) {
            throw new McpError('Tool name is required', Types::ERROR_CODE['InvalidParams']);
        }

        if (! isset($this->toolHandlers[$name])) {
            throw new McpError("Tool not found: {$name}", Types::ERROR_CODE['InvalidParams']);
        }

        $toolParams = $params['params'] ?? [];
        $handler = $this->toolHandlers[$name];

        try {
            return $handler($toolParams);
        } catch (Throwable $e) {
            throw new McpError("Tool execution failed: {$e->getMessage()}", Types::ERROR_CODE['InternalError']);
        }
    }

    /**
     * Handle a listTools request.
     *
     * @param array $params the request parameters
     * @return array the list of tools
     * @throws McpError if the request fails
     */
    public function handleListTools(array $params): array
    {
        if (! $this->initialized) {
            throw new McpError('Server not initialized', Types::ERROR_CODE['ServerNotInitialized']);
        }

        $tools = [];
        foreach ($this->toolHandlers as $name => $handler) {
            $tools[] = [
                'name' => $name,
                'schema' => $this->toolSchemas[$name] ?? null,
            ];
        }

        return ['tools' => $tools];
    }

    /**
     * Handle a listResources request.
     *
     * @param array $params the request parameters
     * @return array the list of resources
     * @throws McpError if the request fails
     */
    public function handleListResources(array $params): array
    {
        if (! $this->initialized) {
            throw new McpError('Server not initialized', Types::ERROR_CODE['ServerNotInitialized']);
        }

        $resources = [];
        foreach ($this->resourceTemplates as $scheme => $template) {
            if ($template->canList()) {
                $resources[] = [
                    'scheme' => $scheme,
                    'template' => $template->getTemplate(),
                ];
            }
        }

        return ['resources' => $resources];
    }

    /**
     * Handle a readResource request.
     *
     * @param array $params the request parameters
     * @return array the resource content
     * @throws McpError if the request fails
     */
    public function handleReadResource(array $params): array
    {
        if (! $this->initialized) {
            throw new McpError('Server not initialized', Types::ERROR_CODE['ServerNotInitialized']);
        }

        $uri = $params['uri'] ?? null;
        if (! $uri) {
            throw new McpError('Resource URI is required', Types::ERROR_CODE['InvalidParams']);
        }

        // Parse the URI to get the scheme and parameters
        $parsedUrl = parse_url($uri);
        $scheme = $parsedUrl['scheme'] ?? null;

        if (! $scheme || ! isset($this->resourceHandlers[$scheme])) {
            throw new McpError("Resource scheme not supported: {$scheme}", Types::ERROR_CODE['InvalidParams']);
        }

        $template = $this->resourceTemplates[$scheme];
        $handler = $this->resourceHandlers[$scheme];

        // Extract parameters from the URI using the template
        $params = $template->extractParams($uri);

        try {
            return $handler($uri, $params);
        } catch (Throwable $e) {
            throw new McpError("Resource read failed: {$e->getMessage()}", Types::ERROR_CODE['InternalError']);
        }
    }

    /**
     * Handle a listPrompts request.
     *
     * @param array $params the request parameters
     * @return array the list of prompts
     * @throws McpError if the request fails
     */
    public function handleListPrompts(array $params): array
    {
        if (! $this->initialized) {
            throw new McpError('Server not initialized', Types::ERROR_CODE['ServerNotInitialized']);
        }

        $prompts = [];
        foreach ($this->promptHandlers as $name => $prompt) {
            $prompts[] = [
                'name' => $name,
                'schema' => $this->promptSchemas[$name] ?? null,
            ];
        }

        return ['prompts' => $prompts];
    }

    /**
     * Handle a getPrompt request.
     *
     * @param array $params the request parameters
     * @return array the prompt definition
     * @throws McpError if the request fails
     */
    public function handleGetPrompt(array $params): array
    {
        if (! $this->initialized) {
            throw new McpError('Server not initialized', Types::ERROR_CODE['ServerNotInitialized']);
        }

        $name = $params['name'] ?? null;
        if (! $name) {
            throw new McpError('Prompt name is required', Types::ERROR_CODE['InvalidParams']);
        }

        if (! isset($this->promptHandlers[$name])) {
            throw new McpError("Prompt not found: {$name}", Types::ERROR_CODE['InvalidParams']);
        }

        return [
            'prompt' => $this->promptHandlers[$name],
            'schema' => $this->promptSchemas[$name] ?? null,
        ];
    }
}
