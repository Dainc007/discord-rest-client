<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Resources;

use Dainc007\DiscordRestClient\Contracts\Transport;
use Dainc007\DiscordRestClient\Exceptions\DiscordApiException;
use Dainc007\DiscordRestClient\Http\Route;
use InvalidArgumentException;

/**
 * Shared plumbing for the resource classes.
 */
abstract class Resource
{
    public function __construct(
        protected readonly Transport $transport,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $body
     * @param  array<string, scalar>  $query
     * @return array<array-key, mixed>
     */
    protected function requestArray(Route $route, ?array $body = null, array $query = [], ?string $auditReason = null): array
    {
        $result = $this->transport->send($route, $body, $query, $auditReason);

        if (! is_array($result)) {
            throw new DiscordApiException(sprintf('Discord returned an unexpected body for %s.', $route->routeKey()));
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>|null  $body
     */
    protected function requestNothing(Route $route, ?array $body = null, ?string $auditReason = null): void
    {
        $this->transport->send($route, $body, [], $auditReason);
    }

    /**
     * @template T
     *
     * @param  array<array-key, mixed>  $items
     * @param  callable(array<array-key, mixed>): T  $map
     * @return list<T>
     */
    protected function mapList(array $items, callable $map): array
    {
        $mapped = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $mapped[] = $map($item);
            }
        }

        return $mapped;
    }

    /**
     * Turn [id => position] into the body Discord's reorder endpoints expect.
     *
     * @param  array<string, int>  $positions
     * @return list<array{id: string, position: int}>
     */
    protected function positionsBody(array $positions): array
    {
        if ($positions === []) {
            throw new InvalidArgumentException('Reordering needs at least one id => position pair.');
        }

        $body = [];

        foreach ($positions as $id => $position) {
            if ($position < 0) {
                throw new InvalidArgumentException("Position for [{$id}] must not be negative.");
            }

            $body[] = ['id' => (string) $id, 'position' => $position];
        }

        return $body;
    }
}
