<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Tests\Support;

use Dainc007\DiscordRestClient\Contracts\Clock;

/**
 * Clock that moves only when slept on, recording each sleep.
 */
final class FakeClock implements Clock
{
    /** @var list<float> */
    public array $sleeps = [];

    public function __construct(
        private float $now = 1_000_000.0,
    ) {}

    public function now(): float
    {
        return $this->now;
    }

    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $this->sleeps[] = $seconds;
        $this->now += $seconds;
    }

    public function advance(float $seconds): void
    {
        $this->now += $seconds;
    }

    public function totalSlept(): float
    {
        return array_sum($this->sleeps);
    }
}
