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

abstract class Result implements McpModel
{
    public function jsonSerialize(): mixed
    {
        return array_filter(get_object_vars($this), function ($value) {
            return $value !== null;
        });
    }
}
