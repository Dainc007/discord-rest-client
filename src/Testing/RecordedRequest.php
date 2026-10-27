<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Testing;

use Dainc007\DiscordRestClient\Http\Route;

/**
 * One call captured by the fake.
 */
final readonly class RecordedRequest
{
    /**
     * @param  array<array-key, mixed>|null  $body
     * @param  array<string, scalar>  $query
     */
    public function __construct(
        public Route $route,
        public ?array $body,
        public array $query,
        public ?string $auditReason,
    ) {}

    public function method(): string
    {
        return $this->route->method;
    }

    public function path(): string
    {
        return $this->route->path();
    }

    /**
     * e.g. "PUT /guilds/{guild}/members/{user}/roles/{role}"
     */
    public function routeKey(): string
    {
        return $this->route->routeKey();
    }

    public function parameter(string $name): ?string
    {
        return $this->route->parameters[$name] ?? null;
    }
}
