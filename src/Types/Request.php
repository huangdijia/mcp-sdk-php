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

use ModelContextProtocol\SDK\Types;
use Psr\Http\Message\ServerRequestInterface;

class Request implements McpModel
{
    public function __construct(
        public int|string $id,
        public string $method,
        public ?array $params = null,
        public string $jsonrpc = Types::JSONRPC_VERSION,
    ) {
    }

    public function toResponse(): Response
    {
        return new Response(
            id: $this->id,
            jsonrpc: $this->jsonrpc,
        );
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
        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

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

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return self::fromArray($data);
    }
}
