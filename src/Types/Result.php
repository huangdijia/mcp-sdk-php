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
use stdClass;

class Result implements JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        $vars = get_object_vars($this);
        return ! empty($vars) ? $vars : new stdClass();
    }
}
