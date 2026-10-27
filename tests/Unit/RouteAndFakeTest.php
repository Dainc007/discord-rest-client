<?php

declare(strict_types=1);

use Dainc007\DiscordRestClient\Exceptions\MissingPermissionException;
use Dainc007\DiscordRestClient\Http\Route;
use Dainc007\DiscordRestClient\Testing\ClientFake;
use Dainc007\DiscordRestClient\Testing\RecordedRequest;
use PHPUnit\Framework\AssertionFailedError;

it('builds paths, route keys and major parameter keys', function (): void {
    $route = Route::put('/guilds/{guild}/members/{user}/roles/{role}', ['guild' => '1', 'user' => '2', 'role' => '3']);

    expect($route->path())->toBe('/guilds/1/members/2/roles/3')
        ->and($route->routeKey())->toBe('PUT /guilds/{guild}/members/{user}/roles/{role}')
        ->and($route->majorParameterKey())->toBe('guild=1')
        ->and($route->isWrite())->toBeTrue()
        ->and(Route::get('/guilds/{guild}', ['guild' => '1'])->isWrite())->toBeFalse()
        ->and(Route::get('/channels/{channel}', ['channel' => '9'])->majorParameterKey())->toBe('channel=9');
});

it('throws for a missing route parameter', function (): void {
    Route::get('/guilds/{guild}')->path();
})->throws(InvalidArgumentException::class, 'Missing route parameter [guild]');

it('lets the fake throw a queued exception', function (): void {
    $discord = new ClientFake([new MissingPermissionException('nope', 403, 50013)]);

    expect(fn () => $discord->members()->addRole('1', '2', '3'))->toThrow(MissingPermissionException::class);
    $discord->assertSentCount(1);
});

it('lets the fake build a response from the request', function (): void {
    $discord = new ClientFake([fn (RecordedRequest $r): array => rolePayload(['name' => $r->body['name']])]);

    expect($discord->roles()->create('1', 'Echo')->name)->toBe('Echo');
});

it('fails clearly when no fake response is queued', function (): void {
    (new ClientFake)->guilds()->roles('1');
})->throws(LogicException::class, 'No fake Discord response queued for GET /guilds/{guild}/roles');

it('fails assertSent and assertNotSent as expected', function (): void {
    $discord = new ClientFake([null]);
    $discord->members()->kick('1', '2');

    $discord->assertNotSent('PUT /guilds/{guild}/bans/{user}');
    $discord->assertSent(fn (RecordedRequest $r): bool => $r->method() === 'DELETE');

    expect(fn () => $discord->assertSent('PUT /guilds/{guild}/bans/{user}'))->toThrow(AssertionFailedError::class);
    expect(fn () => $discord->assertNothingSent())->toThrow(AssertionFailedError::class);
});
