<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Tests\Support;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * A network failure (timeout, refused connection), like Guzzle's ConnectException.
 */
final class ConnectionFailed extends RuntimeException implements NetworkExceptionInterface
{
    public function getRequest(): RequestInterface
    {
        return new Request('GET', 'https://discord.com/api/v10');
    }
}
