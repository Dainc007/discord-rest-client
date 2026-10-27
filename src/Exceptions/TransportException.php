<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Exceptions;

/**
 * The HTTP client failed before Discord answered (DNS, TLS, timeout), or the
 * response body was not valid JSON.
 */
final class TransportException extends DiscordException {}
