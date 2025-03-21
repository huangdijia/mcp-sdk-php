<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Your name <your-mail@gmail.com>
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
    ];

    /**
     * JSON-RPC version used by the protocol.
     */
    public const JSONRPC_VERSION = '2.0';

    /**
     * Error codes defined by the protocol.
     */
    public const ERROR_CODE = [
        'ParseError' => -32700,
        'InvalidRequest' => -32600,
        'MethodNotFound' => -32601,
        'InvalidParams' => -32602,
        'InternalError' => -32603,
        'ServerNotInitialized' => -32002,
        'UnknownErrorCode' => -32001,
        'RequestCancelled' => -32800,
        'ContentModified' => -32801,
        'RequestTimeout' => -32802,
        'RequestFailed' => -32803,
    ];

    /**
     * Logging levels.
     */
    public const LOGGING_LEVEL = [
        'Error' => 1,
        'Warning' => 2,
        'Info' => 3,
        'Debug' => 4,
    ];
}
