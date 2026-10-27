<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Exceptions;

/**
 * 404 Not Found: the guild, channel, role, member or ban does not exist (or the bot cannot see it).
 */
final class UnknownResourceException extends DiscordException {}
