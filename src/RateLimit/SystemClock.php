<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\RateLimit;

use Dainc007\DiscordRestClient\Contracts\Clock;

/**
 * Real wall-clock time and real sleeping.
 */
final class SystemClock implements Clock
{
    public function now(): float
    {
        return microtime(true);
    }

    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        usleep((int) ceil($seconds * 1_000_000));
    }
}
