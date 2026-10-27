<?php

declare(strict_types=1);

use Dainc007\DiscordRestClient\Client;
use Dainc007\DiscordRestClient\Tests\Support\FakeClock;
use Dainc007\DiscordRestClient\Tests\Support\MockHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

/**
 * A real Client wired to a mocked PSR-18 client and a fake clock, so the
 * whole HTTP path (headers, rate limits, errors) runs without the network.
 *
 * @param  list<Response>  $responses
 * @return array{0: Client, 1: MockHttpClient, 2: FakeClock}
 */
function discordWithHttp(array $responses, bool $membersIntent = false, int $maxRetries = 3, float $maxRetryAfterSeconds = 60.0): array
{
    $http = new MockHttpClient($responses);
    $clock = new FakeClock;
    $factory = new HttpFactory;

    $client = Client::make(
        token: 'test-token',
        membersIntent: $membersIntent,
        httpClient: $http,
        requestFactory: $factory,
        streamFactory: $factory,
        clock: $clock,
        maxRetries: $maxRetries,
        maxRetryAfterSeconds: $maxRetryAfterSeconds,
    );

    return [$client, $http, $clock];
}

/**
 * @param  array<string, string>  $headers
 */
function jsonResponse(mixed $body, int $status = 200, array $headers = []): Response
{
    return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body, JSON_THROW_ON_ERROR));
}

/**
 * @return array<string, mixed>
 */
function rolePayload(array $overrides = []): array
{
    return $overrides + [
        'id' => '111',
        'name' => 'Admins',
        'color' => 0x3498DB,
        'hoist' => true,
        'position' => 3,
        'permissions' => '268435456',
        'managed' => false,
        'mentionable' => false,
    ];
}

/**
 * @return array<string, mixed>
 */
function memberPayload(string $id = '900', array $overrides = []): array
{
    return $overrides + [
        'user' => ['id' => $id, 'username' => 'player'.$id, 'global_name' => null],
        'nick' => null,
        'roles' => ['111'],
        'joined_at' => '2026-01-15T10:00:00.000000+00:00',
    ];
}
