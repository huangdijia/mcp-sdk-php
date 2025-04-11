<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Server\Transport;

use ModelContextProtocol\SDK\Server\Transport\Traits\InteractsWithCallbacks;
use ModelContextProtocol\SDK\Shared\Transport;

abstract class AbstractTransport implements Transport
{
    use InteractsWithCallbacks;

    public function close(): void
    {
        $this->handleClose();
    }
}
