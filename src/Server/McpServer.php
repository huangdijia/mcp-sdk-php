<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Server;

use ModelContextProtocol\SDK\Exceptions\McpError;
use ModelContextProtocol\SDK\Shared\ResourceTemplate;
use ModelContextProtocol\SDK\Types;
use stdClass;
use Throwable;

/**
 * A high-level MCP server that provides a simpler API for creating MCP servers.
 */
class McpServer extends Server
{
    /**
     * 收到请求时的回调.
     *
     * @var callable|null
     */
    public $onRequest;

    /**
     * 请求处理完成后的回调.
     *
     * @var callable|null
     */
    public $onResponse;

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

        // Set up shutdown handler to ensure clean resource release
        register_shutdown_function([$this, 'shutdown']);
    }

    /**
     * Clean up resources when the server is shutting down.
     */
    public function shutdown(): void
    {
        if ($this->transport) {
            $this->logger->info('Server shutting down, cleaning up resources');
            $this->disconnect();
        }

        // Clear handlers and other resources
        $this->toolHandlers = [];
        $this->resourceHandlers = [];
        $this->promptHandlers = [];
    }

    /**
     * Register a tool with the server.
     *
     * @param string $name the tool name
     * @param callable $handler the tool handler function
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
                'required' => [],
                '$schema' => 'http://json-schema.org/draft-07/schema#',
            ],
        ], $definition);

        $this->capabilities['tools'] ??= new stdClass();

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

        $this->capabilities['resources'] ??= new stdClass();

        return $this;
    }

    /**
     * Register a prompt with the server.
     *
     * @param string $name the prompt name
     * @param callable $handler the prompt handler function
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

        $this->capabilities['prompts'] ??= new stdClass();

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
            // Validate arguments against tool definition if available
            if (isset($this->toolDefinitions[$name]['inputSchema']['properties'])) {
                $this->validateToolArguments($name, $arguments);
            }

            $result = $handler($arguments);

            // Handle different protocol versions
            $clientVersion = $this->getClientVersion();
            $protocolVersion = $clientVersion['protocolVersion'] ?? Types::LATEST_PROTOCOL_VERSION;

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
                                    : json_encode($result['toolResult'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
        } catch (McpError $e) {
            // Rethrow McpError exceptions directly
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Tool execution error', [
                'tool' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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
        if ($parsedUrl === false) {
            throw new McpError("Invalid URI format: {$uri}", Types::ERROR_CODE['InvalidParams']);
        }

        $scheme = $parsedUrl['scheme'] ?? null;
        if (! $scheme) {
            throw new McpError("Missing scheme in URI: {$uri}", Types::ERROR_CODE['InvalidParams']);
        }

        if (! isset($this->resourceHandlers[$scheme])) {
            throw new McpError("Resource scheme not supported: {$scheme}", Types::ERROR_CODE['InvalidParams']);
        }

        $template = $this->resourceTemplates[$scheme];
        $handler = $this->resourceHandlers[$scheme];

        try {
            // Validate URI against template
            if (! $template->matchesUri($uri)) {
                throw new McpError("URI does not match template format: {$uri}", Types::ERROR_CODE['InvalidParams']);
            }

            $extractedParams = $template->extractParams($uri);

            // Log resource access
            $this->logger->info('Resource access', [
                'uri' => $uri,
                'scheme' => $scheme,
                'params' => $extractedParams,
            ]);

            $content = $handler($uri, $extractedParams);

            // Ensure content is properly formatted
            if (! isset($content['contents']) || ! is_array($content['contents'])) {
                throw new McpError('Invalid resource content format', Types::ERROR_CODE['InternalError']);
            }

            // Validate content structure
            foreach ($content['contents'] as $item) {
                if (! isset($item['type'])) {
                    throw new McpError('Content item missing type', Types::ERROR_CODE['InternalError']);
                }

                if ($item['type'] === 'text' && ! isset($item['text'])) {
                    throw new McpError('Text content missing text field', Types::ERROR_CODE['InternalError']);
                }
                if ($item['type'] === 'image' && ! isset($item['url'])) {
                    throw new McpError('Image content missing url field', Types::ERROR_CODE['InternalError']);
                }
            }

            return $content;
        } catch (McpError $e) {
            // Rethrow McpError with original code
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Resource read failed', [
                'uri' => $uri,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
     * @return bool whether the notification was sent
     */
    public function notifyResourceUpdated(string $uri): bool
    {
        if (! $this->initialized || ! $this->transport) {
            return false;
        }

        // Check if client supports this notification
        if (! $this->clientSupports('resources.updated')) {
            $this->logger->info('Client does not support resource update notifications');
            return false;
        }

        try {
            $this->notify(Types::NOTIFICATION['ResourceUpdated'], [
                'uri' => $uri,
                'timestamp' => time(),
            ]);
            return true;
        } catch (Throwable $e) {
            $this->logger->error('Failed to send resource update notification', [
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Notify clients about changes to the resource list.
     *
     * @return bool whether the notification was sent
     */
    public function notifyResourceListChanged(): bool
    {
        if (! $this->initialized || ! $this->transport) {
            return false;
        }

        // Check if client supports this notification
        if (! $this->clientSupports('resources.listChanged')) {
            $this->logger->info('Client does not support resource list change notifications');
            return false;
        }

        try {
            $this->notify(Types::NOTIFICATION['ResourceListChanged'], [
                'timestamp' => time(),
            ]);
            return true;
        } catch (Throwable $e) {
            $this->logger->error('Failed to send resource list change notification', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Notify clients about changes to the tool list.
     *
     * @return bool whether the notification was sent
     */
    public function notifyToolListChanged(): bool
    {
        if (! $this->initialized || ! $this->transport) {
            return false;
        }

        // Check if client supports this notification
        if (! $this->clientSupports('tools.listChanged')) {
            $this->logger->info('Client does not support tool list change notifications');
            return false;
        }

        try {
            $this->notify(Types::NOTIFICATION['ToolListChanged'], [
                'timestamp' => time(),
            ]);
            return true;
        } catch (Throwable $e) {
            $this->logger->error('Failed to send tool list change notification', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Notify clients about changes to the prompt list.
     *
     * @return bool whether the notification was sent
     */
    public function notifyPromptListChanged(): bool
    {
        if (! $this->initialized || ! $this->transport) {
            return false;
        }

        // Check if client supports this notification
        if (! $this->clientSupports('prompts.listChanged')) {
            $this->logger->info('Client does not support prompt list change notifications');
            return false;
        }

        try {
            $this->notify(Types::NOTIFICATION['PromptListChanged'], [
                'timestamp' => time(),
            ]);
            return true;
        } catch (Throwable $e) {
            $this->logger->error('Failed to send prompt list change notification', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
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
            $capabilities['resources']['updated'] = true;

            // Add subscribe capability if any resource templates support it
            $hasSubscribable = false;
            foreach ($this->resourceTemplates as $template) {
                if ($template->subscribable ?? false) {
                    $hasSubscribable = true;
                    break;
                }
            }

            if ($hasSubscribable) {
                $capabilities['resources']['subscribe'] = true;
            }
        }

        // 添加工具能力（如果已定义工具）
        if (! empty($this->toolDefinitions)) {
            $capabilities['tools']['listChanged'] = true;
        }

        // 添加提示词能力（如果已定义提示词）
        if (! empty($this->promptDefinitions)) {
            $capabilities['prompts']['listChanged'] = true;
        }

        // Add protocol version capabilities
        $capabilities['protocolVersions'] = Types::SUPPORTED_PROTOCOL_VERSIONS;

        return $capabilities;
    }

    /**
     * Validate tool arguments against the tool's input schema.
     *
     * @param string $toolName the tool name
     * @param array $arguments the arguments to validate
     * @throws McpError if validation fails
     */
    private function validateToolArguments(string $toolName, array $arguments): void
    {
        $properties = $this->toolDefinitions[$toolName]['inputSchema']['properties'] ?? [];
        $required = $this->toolDefinitions[$toolName]['inputSchema']['required'] ?? [];

        // Check required parameters
        foreach ($required as $param) {
            if (! isset($arguments[$param])) {
                throw new McpError("Missing required parameter: {$param}", Types::ERROR_CODE['InvalidParams']);
            }
        }

        // Basic type validation
        foreach ($arguments as $key => $value) {
            if (! isset($properties[$key])) {
                continue; // Skip unknown parameters
            }

            $type = $properties[$key]['type'] ?? null;
            if ($type && ! $this->validateParameterType($value, $type)) {
                throw new McpError("Invalid type for parameter '{$key}': expected {$type}", Types::ERROR_CODE['InvalidParams']);
            }
        }
    }

    /**
     * Validate a parameter value against an expected type.
     *
     * @param mixed $value the value to validate
     * @param string $type the expected type
     * @return bool whether the value matches the expected type
     */
    private function validateParameterType($value, string $type): bool
    {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'number':
                return is_numeric($value);
            case 'integer':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'boolean':
                return is_bool($value);
            case 'array':
                return is_array($value);
            case 'object':
                return is_object($value) || (is_array($value) && array_keys($value) !== range(0, count($value) - 1));
            default:
                return true; // Unknown types are accepted
        }
    }
}
