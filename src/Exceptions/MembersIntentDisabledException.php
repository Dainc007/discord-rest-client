<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Exceptions;

/**
 * Thrown before any request when listing every guild member is attempted
 * while the client's members intent option is off.
 */
final class MembersIntentDisabledException extends DiscordException
{
    public static function make(): self
    {
        return new self(
            'Listing all guild members needs the "Server Members" privileged intent. '
            .'Turn on "Server Members Intent" for this application in the Discord developer portal '
            .'(Bot > Privileged Gateway Intents), then create the client with membersIntent: true. '
            .'Reading one member and searching by name work without it.',
        );
    }
}
