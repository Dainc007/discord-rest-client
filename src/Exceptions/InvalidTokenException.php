<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Exceptions;

/**
 * 401 Unauthorized: the bot token is missing, wrong or revoked. Never retried.
 */
final class InvalidTokenException extends DiscordException {}
