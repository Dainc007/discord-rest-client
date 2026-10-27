<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Exceptions;

use Throwable;

/**
 * 429 Too Many Requests that the client gave up on: retries ran out, or
 * Discord asked for a wait longer than the client is allowed to block.
 */
final class RateLimitedException extends DiscordException
{
    /**
     * @param  array<array-key, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly float $retryAfter,
        public readonly bool $isGlobal = false,
        public readonly ?string $scope = null,
        int $httpStatus = 429,
        ?int $discordCode = null,
        array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $discordCode, $errors, $previous);
    }
}
