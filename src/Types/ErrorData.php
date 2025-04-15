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

use Throwable;

class ErrorData implements McpModel
{
    public function __construct(
        public int $code,
        public string $message,
        public ?array $data = null,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return array_filter([
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
        ]);
    }

    public function withData(array $data): self
    {
        return new self($this->code, $this->message, $data);
    }

    public static function fromThrowable(Throwable $throwable): self
    {
        return new self($throwable->getCode(), $throwable->getMessage());
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['code'] ?? 0,
            $data['message'] ?? '',
            $data['data'] ?? null,
        );
    }
}
