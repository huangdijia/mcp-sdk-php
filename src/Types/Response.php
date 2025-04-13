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

class Response implements McpModel
{
    public function __construct(
        public int|string $id,
        public ?array $result = null,
        public ?ErrorData $error = null,
        public string $jsonrpc = '2.0',
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return array_filter([
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id,
            'result' => $this->result,
            'error' => $this->error,
        ]);
    }
}
