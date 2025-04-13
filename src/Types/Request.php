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

use Psr\Http\Message\ServerRequestInterface;

class Request implements McpModel
{
    public function __construct(
        public int|string $id,
        public string $method,
        public ?array $params = null,
        public string $jsonrpc = '2.0',
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return array_filter([
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id,
            'method' => $this->method,
            'params' => $this->params,
        ]);
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? 0,
            $data['method'] ?? '',
            $data['params'] ?? null,
        );
    }
}
