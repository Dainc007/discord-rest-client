<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Exceptions;

/**
 * 403 Forbidden: the bot lacks a permission, or the target sits above the bot's highest role. Never retried.
 */
final class MissingPermissionException extends DiscordException {}
