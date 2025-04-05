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

class EmbeddedResource
{
    public function __construct(
        public ResourceContents $resource,
        public string $type = 'resource',
    ) {
    }

    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? 'resource';
        $resourceData = $data['resource'] ?? [];
        $resource = null;

        if (isset($resourceData['text'])) {
            $resource = TextResourceContents::fromArray($resourceData);
        } else {
            $resource = BlobResourceContents::fromArray($resourceData);
        }

        return new self(
            resource: $resource,
            type: $type,
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'resource' => $this->resource,
            'type' => $this->type,
        ];
    }
}
