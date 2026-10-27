<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Exceptions;

/**
 * Any other non-2xx response from Discord (400 validation errors, 5xx).
 */
final class DiscordApiException extends DiscordException {}
