<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Enums;

/**
 * Whether a channel permission overwrite targets a role or a single member.
 */
enum OverwriteType: int
{
    case Role = 0;
    case Member = 1;
}
