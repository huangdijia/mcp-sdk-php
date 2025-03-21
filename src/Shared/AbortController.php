<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Your name <your-mail@gmail.com>
 */

namespace ModelContextProtocol\SDK\Shared;

/**
 * The AbortController interface is used to abort one or more requests as and when desired.
 */
class AbortController
{
    /**
     * @var AbortSignal the signal object
     */
    private AbortSignal $signal;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->signal = new AbortSignal();
    }

    /**
     * Get the signal object.
     *
     * @return AbortSignal the signal object
     */
    public function signal(): AbortSignal
    {
        return $this->signal;
    }

    /**
     * Abort the request.
     */
    public function abort(): void
    {
        $this->signal->abort();
    }
}
