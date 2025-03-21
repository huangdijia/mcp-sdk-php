<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Your name <your-mail@gmail.com>
 */

namespace ModelContextProtocol\SDK\Exceptions;

use ModelContextProtocol\SDK\Types;

/**
 * Exception thrown when a request is cancelled.
 */
class RequestCancelledError extends McpError
{
    /**
     * Constructor.
     *
     * @param string $message the error message
     * @param mixed $data additional error data
     */
    public function __construct(string $message = 'Request cancelled', $data = null)
    {
        parent::__construct($message, Types::ERROR_CODE['RequestCancelled'], $data);
    }
}
