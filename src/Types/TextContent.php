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

class TextContent extends Content
{
    public function __construct(
        public string $text,
    ) {
        parent::__construct('text');
    }

    public static function fromArray(array $data): self
    {
        return new self(
            text: $data['text'] ?? '',
        );
    }

    public function jsonSerialize(): mixed
    {
        return array_merge(parent::jsonSerialize(), [
            'text' => $this->text,
        ]);
    }
}
