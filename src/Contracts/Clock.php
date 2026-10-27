<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Contracts;

/**
 * Source of time for the rate limiter, so waits can be faked in tests.
 */
interface Clock
{
    /**
     * Current Unix time in seconds, with sub-second precision.
     */
    public function now(): float;

    /**
     * Block for the given number of seconds.
     */
    public function sleep(float $seconds): void;
}
