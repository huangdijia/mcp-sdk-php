<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Your name <your-mail@gmail.com>
 */

namespace ModelContextProtocol\SDK\Types;

use InvalidArgumentException;

class CallToolResult extends Result
{
    public function __construct(
        public array $content,
        public ?bool $isError = null,
    ) {
    }

    public static function fromResponseData(array $data): self
    {
        $contentData = $data['content'] ?? [];
        $isError = $data['isError'] ?? false;
        $content = [];

        foreach ($contentData as $item) {
            if (! is_array($item) || ! isset($item['type'])) {
                throw new InvalidArgumentException('Invalid item in CallToolResult content');
            }

            $type = $item['type'];
            $content[] = match ($type) {
                'text' => TextContent::fromArray($item),
                'image' => ImageContent::fromArray($item),
                'audio' => AudioContent::fromArray($item),
                'resource' => EmbeddedResource::fromArray($item),
                default => throw new InvalidArgumentException("Unknown content type: {$type} in CallToolResult")
            };
        }

        return new self(
            content: $content,
            isError: $isError,
        );
    }
}
