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
     * @var array<string, array> tool definitions by name
     */
    private array $toolDefinitions = [];

    /**
     * @var array<string, callable> resource handlers by scheme
     */
    private array $resourceHandlers = [];

    /**
     * @var array<string, ResourceTemplate> resource templates by scheme
     */
    private array $resourceTemplates = [];

    /**
     * @var array<string, callable> prompt handlers by name
     */
    private array $promptHandlers = [];

    /**
     * @var array<string, array> prompt definitions by name
     */
    private array $promptDefinitions = [];

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
        $this->setRequestHandler(Types::METHOD['CallTool'], [$this, 'handleCallTool']);
        $this->setRequestHandler(Types::METHOD['ListTools'], [$this, 'handleListTools']);
        $this->setRequestHandler(Types::METHOD['ListResources'], [$this, 'handleListResources']);
        $this->setRequestHandler(Types::METHOD['ListResourceTemplates'], [$this, 'handleListResourceTemplates']);
        $this->setRequestHandler(Types::METHOD['ReadResource'], [$this, 'handleReadResource']);
        $this->setRequestHandler(Types::METHOD['ListPrompts'], [$this, 'handleListPrompts']);
        $this->setRequestHandler(Types::METHOD['GetPrompt'], [$this, 'handleGetPrompt']);
        $this->setRequestHandler(Types::METHOD['Subscribe'], [$this, 'handleSubscribe']);
        $this->setRequestHandler(Types::METHOD['Unsubscribe'], [$this, 'handleUnsubscribe']);
    }

    /**
     * Register a tool with the server.
     *
     * @param string $name the tool name
     * @param callable $handler the tool handler function
     * @param array $definition the tool definition (including description and inputSchema)
     * @return self for chaining
     */
    public function tool(string $name, callable $handler, array $definition = []): self
    {
        $this->toolHandlers[$name] = $handler;

        // Ensure the tool definition has required properties
        $this->toolDefinitions[$name] = array_merge([
            'name' => $name,
            'description' => '',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [],
            ],
        ], $definition);

        return $this;
    }

    /**
     * Register a resource handler with the server.
     *
     * @param string $scheme the resource scheme
     * @param ResourceTemplate $template the resource template
     * @param callable $handler the resource handler function
     * @return self for chaining
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
     * @param callable $handler the prompt handler function
     * @param array $definition prompt definition (including description and arguments)
     * @return self for chaining
     */
    public function prompt(string $name, callable $handler, array $definition = []): self
    {
        $this->promptHandlers[$name] = $handler;

        // Ensure the prompt definition has required properties
        $this->promptDefinitions[$name] = array_merge([
            'name' => $name,
            'description' => '',
            'arguments' => [],
        ], $definition);

        return $this;
    }

    /**
     * Handle a tools/call request.
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

        $arguments = $params['arguments'] ?? [];
        $handler = $this->toolHandlers[$name];

        try {
            $result = $handler($arguments);

            // Support for protocol version 2024-11-05+
            if (! isset($result['content'])) {
                // Convert legacy result format to new content format
                if (isset($result['toolResult'])) {
                    return [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => is_string($result['toolResult'])
                                    ? $result['toolResult']
                                    : json_encode($result['toolResult']),
                            ],
                        ],
                    ];
                }

                // If no content is returned, create default response
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Tool execution completed successfully.',
                        ],
                    ],
                ];
            }

            return $result;
        } catch (Throwable $e) {
            // Return error in content format
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: {$e->getMessage()}",
                    ],
                ],
                'isError' => true,
            ];
        }
    }

    /**
     * Handle a tools/list request.
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
        foreach ($this->toolDefinitions as $name => $definition) {
            $tools[] = $definition;
        }

        return ['tools' => $tools];
    }

    /**
     * Handle a resources/list request.
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
        $cursor = $params['cursor'] ?? null;

        foreach ($this->resourceTemplates as $scheme => $template) {
            if ($template->listable) {
                $uri = $template->getExampleUri();
                $resources[] = [
                    'uri' => $uri,
                    'name' => $template->name,
                    'description' => $template->description,
                    'mimeType' => $template->mimeType,
                ];
            }
        }

        return [
            'resources' => $resources,
            'nextCursor' => null, // Implement pagination if needed
        ];
    }

    /**
     * Handle a resources/templates/list request.
     *
     * @param array $params the request parameters
     * @return array the list of resource templates
     * @throws McpError if the request fails
     */
    public function handleListResourceTemplates(array $params): array
    {
        if (! $this->initialized) {
            throw new McpError('Server not initialized', Types::ERROR_CODE['ServerNotInitialized']);
        }

        $templates = [];
        $cursor = $params['cursor'] ?? null;

        foreach ($this->resourceTemplates as $scheme => $template) {
            $templates[] = [
                'uriTemplate' => $template->getUriTemplate(),
                'name' => $template->name,
                'description' => $template->description,
                'mimeType' => $template->mimeType,
            ];
        }

        return [
            'resourceTemplates' => $templates,
            'nextCursor' => null, // Implement pagination if needed
        ];
    }

    /**
     * Handle a resources/read request.
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

        // Parse the URI to get the scheme
        $parsedUrl = parse_url($uri);
        $scheme = $parsedUrl['scheme'] ?? null;

        if (! $scheme || ! isset($this->resourceHandlers[$scheme])) {
            throw new McpError("Resource scheme not supported: {$scheme}", Types::ERROR_CODE['InvalidParams']);
        }

        $template = $this->resourceTemplates[$scheme];
        $handler = $this->resourceHandlers[$scheme];

        try {
            $params = $template->extractParams($uri);
            $content = $handler($uri, $params);

            // Ensure content is properly formatted
            if (! isset($content['contents']) || ! is_array($content['contents'])) {
                throw new McpError('Invalid resource content format', Types::ERROR_CODE['InternalError']);
            }

            return $content;
        } catch (Throwable $e) {
            throw new McpError("Resource read failed: {$e->getMessage()}", Types::ERROR_CODE['InternalError']);
        }
    }

    /**
     * Handle a prompts/list request.
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
        $cursor = $params['cursor'] ?? null;

        foreach ($this->promptDefinitions as $name => $definition) {
            $prompts[] = $definition;
        }

        return [
            'prompts' => $prompts,
            'nextCursor' => null, // Implement pagination if needed
        ];
    }

    /**
     * Handle a prompts/get request.
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

        $handler = $this->promptHandlers[$name];
        $arguments = $params['arguments'] ?? [];
        $definition = $this->promptDefinitions[$name];

        try {
            $result = $handler($arguments);

            if (isset($definition['description'])) {
                $result['description'] = $definition['description'];
            }

            return $result;
        } catch (Throwable $e) {
            throw new McpError("Prompt processing failed: {$e->getMessage()}", Types::ERROR_CODE['InternalError']);
        }
    }

    /**
     * Handle a resources/subscribe request.
     *
     * @param array $params the request parameters
     * @return array an empty result
     * @throws McpError if the request fails
     */
    public function handleSubscribe(array $params): array
    {
        if (! $this->initialized) {
            throw new McpError('Server not initialized', Types::ERROR_CODE['ServerNotInitialized']);
        }

        $uri = $params['uri'] ?? null;
        if (! $uri) {
            throw new McpError('Resource URI is required', Types::ERROR_CODE['InvalidParams']);
        }

        // Parse the URI to get the scheme
        $parsedUrl = parse_url($uri);
        $scheme = $parsedUrl['scheme'] ?? null;

        if (! $scheme || ! isset($this->resourceHandlers[$scheme])) {
            throw new McpError("Resource scheme not supported: {$scheme}", Types::ERROR_CODE['InvalidParams']);
        }

        // Check if server supports subscriptions
        $capabilities = $this->getServerCapabilities();
        if (! isset($capabilities['resources']['subscribe']) || ! $capabilities['resources']['subscribe']) {
            throw new McpError('Server does not support resource subscriptions', Types::ERROR_CODE['InvalidParams']);
        }

        // Implementation would normally track subscriptions here

        return [];
    }

    /**
     * Handle a resources/unsubscribe request.
     *
     * @param array $params the request parameters
     * @return array an empty result
     * @throws McpError if the request fails
     */
    public function handleUnsubscribe(array $params): array
    {
        if (! $this->initialized) {
            throw new McpError('Server not initialized', Types::ERROR_CODE['ServerNotInitialized']);
        }

        $uri = $params['uri'] ?? null;
        if (! $uri) {
            throw new McpError('Resource URI is required', Types::ERROR_CODE['InvalidParams']);
        }

        // Implementation would normally remove subscription here

        return [];
    }

    /**
     * Notify clients about a resource update.
     *
     * @param string $uri the resource URI that changed
     */
    public function notifyResourceUpdated(string $uri): void
    {
        if (! $this->initialized || ! $this->transport) {
            return;
        }

        $this->notify(Types::NOTIFICATION['ResourceUpdated'], [
            'uri' => $uri,
        ]);
    }

    /**
     * Notify clients about changes to the resource list.
     */
    public function notifyResourceListChanged(): void
    {
        if (! $this->initialized || ! $this->transport) {
            return;
        }

        $this->notify(Types::NOTIFICATION['ResourceListChanged'], []);
    }

    /**
     * Notify clients about changes to the tool list.
     */
    public function notifyToolListChanged(): void
    {
        if (! $this->initialized || ! $this->transport) {
            return;
        }

        $this->notify(Types::NOTIFICATION['ToolListChanged'], []);
    }

    /**
     * Notify clients about changes to the prompt list.
     */
    public function notifyPromptListChanged(): void
    {
        if (! $this->initialized || ! $this->transport) {
            return;
        }

        $this->notify(Types::NOTIFICATION['PromptListChanged'], []);
    }

    /**
     * Get the server's capabilities.
     *
     * @return array the server capabilities
     */
    public function getServerCapabilities(): array
    {
        // 初始化一个包含所有可能使用的键的基本能力结构
        $capabilities = [
            'resources' => [],
            'tools' => [],
            'prompts' => [],
        ];

        // 添加资源能力（如果已定义资源）
        if (! empty($this->resourceTemplates)) {
            $capabilities['resources']['listChanged'] = true;
        }

        // 添加工具能力（如果已定义工具）
        if (! empty($this->toolDefinitions)) {
            $capabilities['tools']['listChanged'] = true;
        }

        // 添加提示词能力（如果已定义提示词）
        if (! empty($this->promptDefinitions)) {
            $capabilities['prompts']['listChanged'] = true;
        }

        return $capabilities;
    }

    /**
     * Get the capabilities of this server.
     * This is a helper method to access the capabilities from the parent class.
     *
     * @return array the capabilities
     */
    protected function getCapabilities(): array
    {
        // 返回一个基本能力集合
        // 在理想情况下，我们应该能够访问父类的 $capabilities 属性
        // 但由于它是私有的，我们需要提供一个基本实现
        return [
            'resources' => [
                'subscribe' => false,
            ],
            'tools' => [],
            'prompts' => [],
        ];
    }
}
