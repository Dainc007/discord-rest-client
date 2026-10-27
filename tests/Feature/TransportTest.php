<?php

declare(strict_types=1);

use Dainc007\DiscordRestClient\Client;
use Dainc007\DiscordRestClient\Exceptions\DiscordApiException;
use Dainc007\DiscordRestClient\Exceptions\InvalidTokenException;
use Dainc007\DiscordRestClient\Exceptions\MissingPermissionException;
use Dainc007\DiscordRestClient\Exceptions\RateLimitedException;
use Dainc007\DiscordRestClient\Exceptions\TransportException;
use Dainc007\DiscordRestClient\Exceptions\UnknownResourceException;
use Dainc007\DiscordRestClient\Tests\Support\ConnectionFailed;
use Dainc007\DiscordRestClient\Tests\Support\MockHttpClient;
use Dainc007\DiscordRestClient\Tests\Support\RequestRejected;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

it('sends the bot token, user agent and v10 base url', function (): void {
    [$discord, $http] = discordWithHttp([jsonResponse([rolePayload()])]);

    $discord->guilds()->roles('42');

    $request = $http->lastRequest();
    expect((string) $request->getUri())->toBe('https://discord.com/api/v10/guilds/42/roles')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bot test-token')
        ->and($request->getHeaderLine('User-Agent'))->toBe('DiscordBot (https://github.com/dainc007/discord-rest-client, '.Client::VERSION.')')
        ->and($request->hasHeader('X-Audit-Log-Reason'))->toBeFalse()
        ->and($request->hasHeader('Content-Type'))->toBeFalse();
});

it('strips a "Bot " prefix the caller already added to the token', function (): void {
    $http = new MockHttpClient([jsonResponse([])]);
    $factory = new HttpFactory;
    $discord = Client::make('Bot abc', httpClient: $http, requestFactory: $factory, streamFactory: $factory);

    $discord->guilds()->roles('1');

    expect($http->lastRequest()->getHeaderLine('Authorization'))->toBe('Bot abc');
});

it('rejects an empty or whitespace-only token', function (string $token): void {
    Client::make($token);
})->with(['', '   '])->throws(InvalidArgumentException::class, 'bot token is required');

it('sends the audit reason url-encoded, keeping polish characters', function (): void {
    [$discord, $http] = discordWithHttp([new Response(204)]);

    $discord->members()->addRole('42', '900', '111', reason: 'Zmieniono przez Łukasz Żółć');

    $header = $http->lastRequest()->getHeaderLine('X-Audit-Log-Reason');
    expect($header)->toBe(rawurlencode('Zmieniono przez Łukasz Żółć'))
        ->and(rawurldecode($header))->toBe('Zmieniono przez Łukasz Żółć');
});

it('caps the audit reason at 512 characters', function (): void {
    [$discord, $http] = discordWithHttp([new Response(204)]);

    $discord->members()->kick('42', '900', reason: str_repeat('ą', 5000));

    expect(mb_strlen(rawurldecode($http->lastRequest()->getHeaderLine('X-Audit-Log-Reason'))))->toBe(512);
});

it('omits the audit reason header for a blank reason', function (?string $reason): void {
    [$discord, $http] = discordWithHttp([new Response(204)]);

    $discord->members()->kick('42', '900', reason: $reason);

    expect($http->lastRequest()->hasHeader('X-Audit-Log-Reason'))->toBeFalse();
})->with([null, '', '   ']);

it('sends json bodies with unicode intact', function (): void {
    [$discord, $http] = discordWithHttp([jsonResponse(rolePayload(['name' => 'Zawodnicy ąćęłńóśźż']))]);

    $role = $discord->roles()->create('42', 'Zawodnicy ąćęłńóśźż');

    $request = $http->lastRequest();
    expect($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and(json_decode((string) $request->getBody(), true)['name'])->toBe('Zawodnicy ąćęłńóśźż')
        ->and($role->name)->toBe('Zawodnicy ąćęłńóśźż');
});

it('sends an empty json object, not an array, for a ban without message deletion', function (): void {
    [$discord, $http] = discordWithHttp([new Response(204)]);

    $discord->members()->ban('42', '900');

    expect((string) $http->lastRequest()->getBody())->toBe('{}');
});

it('url-encodes path parameters', function (): void {
    [$discord, $http] = discordWithHttp([jsonResponse(memberPayload())]);

    $discord->members()->get('42', '../9 0');

    expect($http->lastRequest()->getUri()->getPath())->toBe('/api/v10/guilds/42/members/..%2F9%200');
});

it('waits for retry-after on a 429 and retries', function (): void {
    [$discord, $http, $clock] = discordWithHttp([
        jsonResponse(['message' => 'You are being rate limited.', 'retry_after' => 1.5, 'global' => false], 429, ['Retry-After' => '2']),
        jsonResponse([rolePayload()]),
    ]);

    $roles = $discord->guilds()->roles('42');

    expect($roles)->toHaveCount(1)
        ->and($http->requests)->toHaveCount(2)
        ->and($clock->totalSlept())->toEqualWithDelta(1.5, 0.0001);
});

it('falls back to the Retry-After header when the 429 body has none', function (): void {
    [$discord, $http, $clock] = discordWithHttp([
        new Response(429, ['Retry-After' => '3']),
        jsonResponse([]),
    ]);

    expect($discord->guilds()->roles('42'))->toBe([])
        ->and($clock->totalSlept())->toEqualWithDelta(3.0, 0.0001);
});

it('pauses every route after a global 429', function (): void {
    [$discord, $http, $clock] = discordWithHttp([
        jsonResponse(['retry_after' => 4, 'global' => true], 429, ['X-RateLimit-Global' => 'true']),
        jsonResponse([]),
        jsonResponse([]),
    ]);

    $discord->guilds()->channels('42');
    $discord->guilds()->roles('42');

    expect($clock->totalSlept())->toEqualWithDelta(4.0, 0.0001)
        ->and($http->requests)->toHaveCount(3);
});

it('gives up after the configured number of retries', function (): void {
    [$discord, $http] = discordWithHttp([
        jsonResponse(['retry_after' => 0.1, 'global' => false, 'code' => 0], 429),
        jsonResponse(['retry_after' => 0.1, 'global' => false, 'code' => 0], 429),
        jsonResponse(['retry_after' => 0.1, 'global' => false, 'code' => 0], 429),
    ], maxRetries: 2);

    try {
        $discord->guilds()->roles('42');
        $this->fail('Expected RateLimitedException');
    } catch (RateLimitedException $exception) {
        expect($exception->httpStatus)->toBe(429)
            ->and($exception->retryAfter)->toBe(0.1)
            ->and($exception->isGlobal)->toBeFalse()
            ->and($exception->discordCode)->toBe(0);
    }

    expect($http->requests)->toHaveCount(3);
});

it('does not retry at all with zero retries', function (): void {
    [$discord, $http] = discordWithHttp([jsonResponse(['retry_after' => 0.1], 429)], maxRetries: 0);

    expect(fn () => $discord->guilds()->roles('42'))->toThrow(RateLimitedException::class)
        ->and($http->requests)->toHaveCount(1);
});

it('does not block when discord asks for a wait longer than the cap', function (): void {
    [$discord, $http, $clock] = discordWithHttp([
        jsonResponse(['retry_after' => 3600, 'global' => false], 429, ['X-RateLimit-Scope' => 'shared']),
    ], maxRetryAfterSeconds: 60);

    try {
        $discord->guilds()->roles('42');
        $this->fail('Expected RateLimitedException');
    } catch (RateLimitedException $exception) {
        expect($exception->retryAfter)->toBe(3600.0)
            ->and($exception->scope)->toBe('shared');
    }

    expect($clock->sleeps)->toBe([])->and($http->requests)->toHaveCount(1);
});

it('never retries a 401 and maps it to InvalidTokenException with the discord code', function (): void {
    [$discord, $http, $clock] = discordWithHttp([
        jsonResponse(['message' => '401: Unauthorized', 'code' => 0], 401),
        jsonResponse([]),
    ]);

    try {
        $discord->guilds()->roles('42');
        $this->fail('Expected InvalidTokenException');
    } catch (InvalidTokenException $exception) {
        expect($exception->httpStatus)->toBe(401)
            ->and($exception->discordCode)->toBe(0)
            ->and($exception->getMessage())->toContain('401: Unauthorized');
    }

    expect($http->requests)->toHaveCount(1)->and($clock->sleeps)->toBe([]);
});

it('never retries a 403 and maps it to MissingPermissionException', function (): void {
    [$discord, $http] = discordWithHttp([
        jsonResponse(['message' => 'Missing Permissions', 'code' => 50013], 403),
        jsonResponse([]),
    ]);

    try {
        $discord->members()->addRole('42', '900', '111');
        $this->fail('Expected MissingPermissionException');
    } catch (MissingPermissionException $exception) {
        expect($exception->discordCode)->toBe(50013)
            ->and($exception->getCode())->toBe(50013);
    }

    expect($http->requests)->toHaveCount(1);
});

it('maps a 404 to UnknownResourceException with the discord code', function (): void {
    [$discord] = discordWithHttp([jsonResponse(['message' => 'Unknown Role', 'code' => 10011], 404)]);

    expect(fn () => $discord->roles()->delete('42', '999'))
        ->toThrow(fn (UnknownResourceException $e) => expect($e->discordCode)->toBe(10011));
});

it('maps a 400 to DiscordApiException carrying the field errors', function (): void {
    $errors = ['name' => ['_errors' => [['code' => 'BASE_TYPE_MAX_LENGTH', 'message' => 'Must be 100 or fewer in length.']]]];
    [$discord] = discordWithHttp([jsonResponse(['message' => 'Invalid Form Body', 'code' => 50035, 'errors' => $errors], 400)]);

    try {
        $discord->roles()->create('42', str_repeat('x', 101));
        $this->fail('Expected DiscordApiException');
    } catch (DiscordApiException $exception) {
        expect($exception->discordCode)->toBe(50035)
            ->and($exception->errors)->toBe($errors);
    }
});

it('maps a 5xx with a non-json body without a discord code', function (): void {
    [$discord] = discordWithHttp([new Response(502, [], '<html>Bad gateway</html>')], maxRetries: 0);

    expect(fn () => $discord->guilds()->get('42'))
        ->toThrow(fn (DiscordApiException $e) => expect($e->httpStatus)->toBe(502)->and($e->discordCode)->toBeNull());
});

it('retries a read after a 5xx with a growing wait', function (): void {
    [$discord, $http, $clock] = discordWithHttp([
        new Response(502),
        new Response(503),
        jsonResponse([rolePayload()]),
    ]);

    expect($discord->guilds()->roles('42'))->toHaveCount(1)
        ->and($http->requests)->toHaveCount(3)
        ->and($clock->sleeps)->toBe([0.5, 1.0]);
});

it('gives up on a read after the configured number of 5xx retries', function (): void {
    [$discord, $http] = discordWithHttp([new Response(502), new Response(502), new Response(502)], maxRetries: 2);

    expect(fn () => $discord->guilds()->roles('42'))->toThrow(DiscordApiException::class)
        ->and($http->requests)->toHaveCount(3);
});

it('never retries a write after a 5xx, so a change is not applied twice', function (): void {
    [$discord, $http] = discordWithHttp([new Response(502)]);

    expect(fn () => $discord->members()->addRole('42', '900', '111'))->toThrow(DiscordApiException::class)
        ->and($http->requests)->toHaveCount(1);
});

it('does not retry a 4xx read', function (): void {
    [$discord, $http] = discordWithHttp([jsonResponse(['message' => 'Invalid Form Body', 'code' => 50035], 400)]);

    expect(fn () => $discord->guilds()->roles('42'))->toThrow(DiscordApiException::class)
        ->and($http->requests)->toHaveCount(1);
});

it('wraps PSR-18 client failures in TransportException', function (): void {
    [$discord] = discordWithHttp([new ConnectionFailed('Connection timed out')], maxRetries: 0);

    expect(fn () => $discord->guilds()->get('42'))->toThrow(TransportException::class, 'Connection timed out');
});

it('retries a read after a network error, sharing the retry count with 5xx', function (): void {
    [$discord, $http, $clock] = discordWithHttp([
        new ConnectionFailed('Connection timed out'),
        new Response(502),
        jsonResponse([rolePayload()]),
    ]);

    expect($discord->guilds()->roles('42'))->toHaveCount(1)
        ->and($http->requests)->toHaveCount(3)
        ->and($clock->sleeps)->toBe([0.5, 1.0]);
});

it('gives up on a read when network errors outlast the retries', function (): void {
    [$discord, $http] = discordWithHttp([new ConnectionFailed('a'), new ConnectionFailed('b')], maxRetries: 1);

    expect(fn () => $discord->guilds()->roles('42'))->toThrow(TransportException::class, 'b')
        ->and($http->requests)->toHaveCount(2);
});

it('fails fast on a client error that is not a network problem', function (): void {
    [$discord, $http] = discordWithHttp([new RequestRejected('Malformed request')]);

    expect(fn () => $discord->guilds()->roles('42'))->toThrow(TransportException::class, 'Malformed request')
        ->and($http->requests)->toHaveCount(1);
});

it('never retries a write after a network error', function (): void {
    [$discord, $http] = discordWithHttp([new ConnectionFailed('Connection timed out')]);

    expect(fn () => $discord->members()->kick('42', '900'))->toThrow(TransportException::class)
        ->and($http->requests)->toHaveCount(1);
});

it('rejects a successful response whose body is not json', function (): void {
    [$discord] = discordWithHttp([new Response(200, [], 'not json')]);

    expect(fn () => $discord->guilds()->get('42'))->toThrow(TransportException::class, 'not valid JSON');
});

it('waits for a bucket that discord reported as exhausted before the next call on that route', function (): void {
    $headers = ['X-RateLimit-Bucket' => 'abc', 'X-RateLimit-Limit' => '5', 'X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset-After' => '2.5'];
    [$discord, $http, $clock] = discordWithHttp([
        jsonResponse([], 200, $headers),
        jsonResponse([], 200, $headers),
    ]);

    $discord->guilds()->roles('42');
    expect($clock->sleeps)->toBe([]);

    $discord->guilds()->roles('42');
    expect($clock->totalSlept())->toEqualWithDelta(2.5, 0.0001);
});

it('keeps buckets separate per guild (major parameter)', function (): void {
    $headers = ['X-RateLimit-Bucket' => 'abc', 'X-RateLimit-Limit' => '5', 'X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset-After' => '2.5'];
    [$discord, $http, $clock] = discordWithHttp([
        jsonResponse([], 200, $headers),
        jsonResponse([], 200, $headers),
    ]);

    $discord->guilds()->roles('42');
    $discord->guilds()->roles('43');

    expect($clock->sleeps)->toBe([]);
});

it('does not wait when the bucket reset time has already passed', function (): void {
    $headers = ['X-RateLimit-Bucket' => 'abc', 'X-RateLimit-Limit' => '5', 'X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset-After' => '1'];
    [$discord, $http, $clock] = discordWithHttp([
        jsonResponse([], 200, $headers),
        jsonResponse([], 200, $headers),
    ]);

    $discord->guilds()->roles('42');
    $clock->advance(1.0);
    $discord->guilds()->roles('42');

    expect($clock->sleeps)->toBe([]);
});

it('handles an empty 204 response to a write', function (): void {
    [$discord, $http] = discordWithHttp([new Response(204)]);

    $discord->roles()->delete('42', '111', reason: 'cleanup');

    expect($http->lastRequest()->getMethod())->toBe('DELETE')
        ->and((string) $http->lastRequest()->getUri())->toEndWith('/guilds/42/roles/111');
});

it('does not wait twice when a 429 hits a bucket it already knows', function (): void {
    $bucket = ['X-RateLimit-Bucket' => 'abc', 'X-RateLimit-Limit' => '5', 'X-RateLimit-Remaining' => '4', 'X-RateLimit-Reset-After' => '1'];
    [$discord, $http, $clock] = discordWithHttp([
        jsonResponse([], 200, $bucket),
        jsonResponse(['retry_after' => 2, 'global' => false], 429, $bucket),
        jsonResponse([], 200, $bucket),
    ]);

    $discord->guilds()->roles('42');
    $discord->guilds()->roles('42');

    expect($clock->totalSlept())->toEqualWithDelta(2.0, 0.0001)
        ->and($http->requests)->toHaveCount(3);
});
