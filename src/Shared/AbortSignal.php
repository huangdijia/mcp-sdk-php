<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Shared;

/**
 * A signal object that allows you to communicate with a request and abort it if required.
 */
class AbortSignal
{
    /**
     * @var callable|null the abort callback
     */
    public $onabort;

    /**
     * @var bool whether the signal has been aborted
     */
    private bool $aborted = false;

    /**
     * Check if the signal has been aborted.
     *
     * @return bool true if the signal has been aborted, false otherwise
     */
    public function aborted(): bool
    {
        return $this->aborted;
    }

    /**
     * Abort the signal.
     */
    public function abort(): void
    {
        if ($this->aborted) {
            return;
        }

        $this->aborted = true;

        if (is_callable($this->onabort)) {
            call_user_func($this->onabort);
        }
    }
}
