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

class ImageContent extends Content
{
    public function __construct(
        public string $data,
        public string $mimeType,
    ) {
        parent::__construct('image');
    }

    public static function fromArray(array $data): self
    {
        return new self(
            data: $data['data'] ?? '',
            mimeType: $data['mimeType'] ?? '',
        );
    }

    public function jsonSerialize(): mixed
    {
        return array_merge(parent::jsonSerialize(), [
            'data' => $this->data,
            'mimeType' => $this->mimeType,
        ]);
    }
}
