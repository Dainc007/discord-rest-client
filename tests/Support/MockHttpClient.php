<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Tests\Support;

use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * PSR-18 client that replays queued responses and keeps every request.
 */
final class MockHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param  list<ResponseInterface|ClientExceptionInterface>  $responses
     */
    public function __construct(
        private array $responses,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->responses === []) {
            throw new LogicException('No mocked response left for '.$request->getMethod().' '.$request->getUri());
        }

        $response = array_shift($this->responses);

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }

    public function lastRequest(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)];
    }
}
