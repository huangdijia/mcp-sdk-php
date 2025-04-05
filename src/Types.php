<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK;

/**
 * Constants and type definitions for the Model Context Protocol.
 */
class Types
{
    /**
     * The latest protocol version supported by this SDK.
     */
    public const LATEST_PROTOCOL_VERSION = '2024-11-05';

    /**
     * List of protocol versions supported by this SDK.
     */
    public const SUPPORTED_PROTOCOL_VERSIONS = [
        self::LATEST_PROTOCOL_VERSION,
        '2024-10-07',
        '0.1.0',
    ];

    /**
     * JSON-RPC version used by the protocol.
     */
    public const JSONRPC_VERSION = '2.0';

    /**
     * Error codes defined by the protocol.
     */
    public const ERROR_CODE = [
        // SDK error codes
        'ConnectionClosed' => -32000,
        'RequestTimeout' => -32001,
        'RequestCancelled' => -32004,
        'RequestFailed' => -32005,

        // Standard JSON-RPC error codes
        'ParseError' => -32700,
        'InvalidRequest' => -32600,
        'MethodNotFound' => -32601,
        'InvalidParams' => -32602,
        'InternalError' => -32603,

        // Custom error codes
        'ServerNotInitialized' => -32002,
        'UnknownErrorCode' => -32099,
    ];

    /**
     * Logging levels.
     */
    public const LOGGING_LEVEL = [
        'Debug' => 'debug',
        'Info' => 'info',
        'Notice' => 'notice',
        'Warning' => 'warning',
        'Error' => 'error',
        'Critical' => 'critical',
        'Alert' => 'alert',
        'Emergency' => 'emergency',
    ];

    /**
     * Message roles.
     */
    public const MESSAGE_ROLE = [
        'User' => 'user',
        'Assistant' => 'assistant',
    ];

    /**
     * Content types.
     */
    public const CONTENT_TYPE = [
        'Text' => 'text',
        'Image' => 'image',
        'Resource' => 'resource',
    ];

    /**
     * Stop reasons.
     */
    public const STOP_REASON = [
        'EndTurn' => 'endTurn',
        'StopSequence' => 'stopSequence',
        'MaxTokens' => 'maxTokens',
    ];

    /**
     * Context inclusion levels.
     */
    public const INCLUDE_CONTEXT = [
        'None' => 'none',
        'ThisServer' => 'thisServer',
        'AllServers' => 'allServers',
    ];

    /**
     * Reference types.
     */
    public const REF_TYPE = [
        'Resource' => 'ref/resource',
        'Prompt' => 'ref/prompt',
    ];

    /**
     * Standard method names.
     */
    public const METHOD = [
        // Client methods
        'Ping' => 'ping',
        'Initialize' => 'initialize',
        'Complete' => 'completion/complete',
        'SetLevel' => 'logging/setLevel',
        'GetPrompt' => 'prompts/get',
        'ListPrompts' => 'prompts/list',
        'ListResources' => 'resources/list',
        'ListResourceTemplates' => 'resources/templates/list',
        'ReadResource' => 'resources/read',
        'Subscribe' => 'resources/subscribe',
        'Unsubscribe' => 'resources/unsubscribe',
        'CallTool' => 'tools/call',
        'ListTools' => 'tools/list',

        // Server methods
        'CreateMessage' => 'sampling/createMessage',
        'ListRoots' => 'roots/list',
    ];

    /**
     * Standard notification methods.
     */
    public const NOTIFICATION = [
        'Cancelled' => 'notifications/cancelled',
        'Progress' => 'notifications/progress',
        'Initialized' => 'notifications/initialized',
        'RootsListChanged' => 'notifications/roots/list_changed',
        'Message' => 'notifications/message',
        'ResourceUpdated' => 'notifications/resources/updated',
        'ResourceListChanged' => 'notifications/resources/list_changed',
        'ToolListChanged' => 'notifications/tools/list_changed',
        'PromptListChanged' => 'notifications/prompts/list_changed',
    ];
}
