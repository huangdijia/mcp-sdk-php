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

class TextResourceContents extends ResourceContents
{
    public function __construct(
        public string $text,
        string $uri,
        ?string $mimeType = null,
    ) {
        parent::__construct($uri, $mimeType);
    }

    public static function fromArray(array $data): self
    {
        $uri = $data['uri'] ?? '';
        $mimeType = $data['mimeType'] ?? null;
        $text = $data['text'] ?? '';

        return new self(
            text: $text,
            uri: $uri,
            mimeType: $mimeType,
        );
    }

    public function jsonSerialize(): mixed
    {
        return array_merge(parent::jsonSerialize(), [
            'text' => $this->text,
        ]);
    }
}
