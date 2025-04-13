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
use Throwable;

class Response implements McpModel
{
    public function __construct(
        public int|string $id,
        public ?array $result = null,
        public ?ErrorData $error = null,
        public string $jsonrpc = Types::JSONRPC_VERSION,
    ) {
    }

    public function withResult(mixed $result): self
    {
        return new self($this->id, $result, $this->error, $this->jsonrpc);
    }

    public function withThrowable(Throwable $throwable): self
    {
        return $this->withError(ErrorData::fromThrowable($throwable));
    }

    public function withError(ErrorData $error): self
    {
        return new self($this->id, $this->result, $error, $this->jsonrpc);
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

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['result'] ?? null,
            $data['error'] ?? null,
        );
    }
}
