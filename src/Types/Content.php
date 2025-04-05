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

use JsonSerializable;

abstract class Content implements JsonSerializable
{
    public function __construct(public string $type)
    {
    }

    public function jsonSerialize(): mixed
    {
        return ['type' => $this->type];
    }
}
