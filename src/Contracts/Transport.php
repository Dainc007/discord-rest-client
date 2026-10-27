<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Contracts;

use Dainc007\DiscordRestClient\Http\Route;

/**
 * Sends one Discord API call and returns the decoded JSON body.
 */
interface Transport
{
    /**
     * @param  array<array-key, mixed>|null  $body  JSON body, or null for none
     * @param  array<string, scalar>  $query
     * @return mixed Decoded JSON, or null for an empty (204) response
     */
    public function send(Route $route, ?array $body = null, array $query = [], ?string $auditReason = null): mixed;
}
