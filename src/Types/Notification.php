<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Types;

class Notification implements McpModel
{
    public function __construct(
        public string $method,
        public ?array $params = null,
        public string $jsonrpc = '2.0',
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return array_filter([
            'jsonrpc' => $this->jsonrpc,
            'method' => $this->method,
            'params' => $this->params,
        ]);
    }
}
