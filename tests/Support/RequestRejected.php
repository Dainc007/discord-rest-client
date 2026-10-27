<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * A PSR-18 failure that is not a network problem, such as a request the client could not send.
 */
final class RequestRejected extends RuntimeException implements ClientExceptionInterface {}
