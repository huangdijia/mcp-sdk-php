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

class BlobResourceContents extends ResourceContents
{
    public function __construct(
        public string $blob,
        string $uri,
        ?string $mimeType = null,
    ) {
        parent::__construct($uri, $mimeType);
    }

    public static function fromArray(array $data): self
    {
        $uri = $data['uri'] ?? '';
        $mimeType = $data['mimeType'] ?? null;
        $blob = $data['blob'] ?? '';

        return new self(
            blob: $blob,
            uri: $uri,
            mimeType: $mimeType,
        );
    }
}
