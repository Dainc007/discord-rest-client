<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for every error this client raises.
 *
 * Errors that come from a Discord response carry the HTTP status, Discord's
 * own JSON error code (e.g. 50013 "Missing Permissions", 10011 "Unknown Role")
 * and the nested field errors Discord returns for invalid bodies.
 */
class DiscordException extends RuntimeException
{
    /**
     * @param  array<array-key, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?int $discordCode = null,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $discordCode ?? $httpStatus, $previous);
    }
}
