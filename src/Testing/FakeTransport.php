<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Testing;

use Dainc007\DiscordRestClient\Contracts\Transport;
use Dainc007\DiscordRestClient\Http\Route;
use LogicException;
use Throwable;

/**
 * Records every call and answers from a queue of canned responses.
 *
 * A response is a decoded JSON array, null (an empty 204), a Throwable to
 * throw, or a callable receiving the RecordedRequest and returning one of those.
 */
final class FakeTransport implements Transport
{
    /** @var list<RecordedRequest> */
    private array $recorded = [];

    /**
     * @param  list<mixed>  $responses
     */
    public function __construct(
        private array $responses = [],
    ) {}

    public function addResponses(mixed ...$responses): void
    {
        foreach ($responses as $response) {
            $this->responses[] = $response;
        }
    }

    public function send(Route $route, ?array $body = null, array $query = [], ?string $auditReason = null): mixed
    {
        $request = new RecordedRequest($route, $body, $query, $auditReason);
        $this->recorded[] = $request;

        if ($this->responses === []) {
            throw new LogicException("No fake Discord response queued for {$route->routeKey()} ({$route->path()}).");
        }

        $response = array_shift($this->responses);

        if (is_callable($response) && ! is_array($response)) {
            $response = $response($request);
        }

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }

    /**
     * @return list<RecordedRequest>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }
}
