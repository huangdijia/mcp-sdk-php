<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Exceptions;

use Exception;

/**
 * Base exception class for MCP errors.
 */
class McpError extends Exception
{
    /**
     * @var mixed additional error data
     */
    protected $data;

    /**
     * Constructor.
     *
     * @param string $message the error message
     * @param int $code the error code
     * @param mixed $data additional error data
     */
    public function __construct(string $message, int $code = 0, $data = null)
    {
        parent::__construct($message, $code);
        $this->data = $data;
    }

    /**
     * Get the additional error data.
     *
     * @return mixed the error data
     */
    public function getData()
    {
        return $this->data;
    }
}
