<?php

declare(strict_types=1);

use Dainc007\DiscordRestClient\Exceptions\MembersIntentDisabledException;
use Dainc007\DiscordRestClient\Testing\ClientFake;
use Dainc007\DiscordRestClient\Testing\RecordedRequest;

it('reads one member', function (): void {
    $discord = new ClientFake([memberPayload('900', ['nick' => 'Kapitan'])]);

    $member = $discord->members()->get('42', '900');

    expect($member->id())->toBe('900')
        ->and($member->displayName())->toBe('Kapitan')
        ->and($member->hasRole('111'))->toBeTrue()
        ->and($member->joinedAt?->format('Y-m-d'))->toBe('2026-01-15');
    $discord->assertSent('GET /guilds/{guild}/members/{user}', fn (RecordedRequest $r): bool => $r->parameter('user') === '900');
});

it('searches members by name with the limit', function (): void {
    $discord = new ClientFake([[memberPayload('1'), memberPayload('2')]]);

    $members = $discord->members()->search('42', 'Łuk', limit: 10);

    expect($members)->toHaveCount(2);
    $discord->assertSent('GET /guilds/{guild}/members/search', fn (RecordedRequest $r): bool => $r->query === ['query' => 'Łuk', 'limit' => 10]);
});

it('returns an empty list when the search finds nobody', function (): void {
    $discord = new ClientFake([[]]);

    expect($discord->members()->search('42', 'nobody'))->toBe([]);
});

it('rejects an empty search query before calling discord', function (string $query): void {
    $discord = new ClientFake;

    expect(fn () => $discord->members()->search('42', $query))->toThrow(InvalidArgumentException::class);
    $discord->assertNothingSent();
})->with(['', '   ']);

it('accepts search limits 1 and 1000 and rejects 0 and 1001', function (int $limit, bool $valid): void {
    $discord = new ClientFake([[]]);

    if ($valid) {
        expect($discord->members()->search('42', 'a', $limit))->toBe([]);
    } else {
        expect(fn () => $discord->members()->search('42', 'a', $limit))->toThrow(InvalidArgumentException::class);
        $discord->assertNothingSent();
    }
})->with([[1, true], [1000, true], [0, false], [1001, false]]);

it('refuses to list all members when the members intent is off, naming the portal setting', function (): void {
    $discord = new ClientFake;

    expect(fn () => $discord->members()->list('42'))
        ->toThrow(MembersIntentDisabledException::class, 'Server Members');
    expect(fn () => iterator_to_array($discord->members()->all('42')))
        ->toThrow(MembersIntentDisabledException::class);
    $discord->assertNothingSent();
});

it('lists one page of members when the members intent is on', function (): void {
    $discord = new ClientFake([[memberPayload('1')]], membersIntent: true);

    expect($discord->members()->list('42', 500, after: '0'))->toHaveCount(1);
    $discord->assertSent('GET /guilds/{guild}/members', fn (RecordedRequest $r): bool => $r->query === ['limit' => 500, 'after' => '0']);
});

it('pages through every member using the last id as the cursor', function (): void {
    $discord = new ClientFake([
        [memberPayload('1'), memberPayload('2')],
        [memberPayload('3'), memberPayload('4')],
        [memberPayload('5')],
    ], membersIntent: true);

    $ids = array_map(fn ($m) => $m->id(), iterator_to_array($discord->members()->all('42', pageSize: 2), false));

    expect($ids)->toBe(['1', '2', '3', '4', '5']);
    $afters = array_map(fn (RecordedRequest $r) => $r->query['after'] ?? null, $discord->recorded());
    expect($afters)->toBe([null, '2', '4']);
});

it('stops paging on an empty guild', function (): void {
    $discord = new ClientFake([[]], membersIntent: true);

    expect(iterator_to_array($discord->members()->all('42')))->toBe([]);
    $discord->assertSentCount(1);
});

it('adds and removes a role with an audit reason', function (): void {
    $discord = new ClientFake([null, null]);

    $discord->members()->addRole('42', '900', '111', reason: 'Linked Discord account');
    $discord->members()->removeRole('42', '900', '111', reason: 'Left team');

    $discord->assertSent('PUT /guilds/{guild}/members/{user}/roles/{role}', fn (RecordedRequest $r): bool => $r->auditReason === 'Linked Discord account' && $r->path() === '/guilds/42/members/900/roles/111');
    $discord->assertSent('DELETE /guilds/{guild}/members/{user}/roles/{role}', fn (RecordedRequest $r): bool => $r->auditReason === 'Left team');
});

it('sets and clears a nickname', function (?string $nickname, ?string $sent): void {
    $discord = new ClientFake([memberPayload('900', ['nick' => $sent])]);

    $member = $discord->members()->setNickname('42', '900', $nickname);

    expect($member->nick)->toBe($sent);
    $discord->assertSent('PATCH /guilds/{guild}/members/{user}', fn (RecordedRequest $r): bool => $r->body === ['nick' => $sent]);
})->with([
    'set' => ['Żółć', 'Żółć'],
    'clear with null' => [null, null],
    'clear with empty string' => ['', null],
    'exactly 32 multibyte characters' => [str_repeat('ż', 32), str_repeat('ż', 32)],
]);

it('rejects a nickname longer than 32 characters', function (): void {
    $discord = new ClientFake;

    expect(fn () => $discord->members()->setNickname('42', '900', str_repeat('ż', 33)))->toThrow(InvalidArgumentException::class);
    $discord->assertNothingSent();
});

it('times a member out and lifts it', function (): void {
    $member = ['user' => ['id' => '900', 'username' => 'anna'], 'roles' => [], 'communication_disabled_until' => '2030-01-01T10:00:00+00:00'];
    $discord = new ClientFake([$member, $member]);
    $until = new DateTimeImmutable('+1 hour');

    $discord->members()->timeout('42', '900', $until, 'spam');
    $discord->members()->timeout('42', '900', null);

    $discord->assertSent('PATCH /guilds/{guild}/members/{user}', fn (RecordedRequest $r): bool => $r->body === ['communication_disabled_until' => $until->format(DATE_ATOM)] && $r->auditReason === 'spam');
    $discord->assertSent('PATCH /guilds/{guild}/members/{user}', fn (RecordedRequest $r): bool => $r->body === ['communication_disabled_until' => null]);
});

it('refuses a timeout longer than 28 days', function (): void {
    $discord = new ClientFake;

    expect(fn () => $discord->members()->timeout('42', '900', new DateTimeImmutable('+29 days')))->toThrow(InvalidArgumentException::class);
    $discord->assertNothingSent();
});

it('kicks, bans and unbans with reasons', function (): void {
    $discord = new ClientFake([null, null, null]);

    $discord->members()->kick('42', '900', 'Spam');
    $discord->members()->ban('42', '900', deleteMessageSeconds: 604800, reason: 'Spam again');
    $discord->members()->unban('42', '900', 'Appeal accepted');

    $discord->assertSent('DELETE /guilds/{guild}/members/{user}', fn (RecordedRequest $r): bool => $r->auditReason === 'Spam');
    $discord->assertSent('PUT /guilds/{guild}/bans/{user}', fn (RecordedRequest $r): bool => $r->body === ['delete_message_seconds' => 604800] && $r->auditReason === 'Spam again');
    $discord->assertSent('DELETE /guilds/{guild}/bans/{user}', fn (RecordedRequest $r): bool => $r->auditReason === 'Appeal accepted');
});

it('rejects ban message deletion outside 0 to 7 days', function (int $seconds): void {
    $discord = new ClientFake;

    expect(fn () => $discord->members()->ban('42', '900', $seconds))->toThrow(InvalidArgumentException::class);
    $discord->assertNothingSent();
})->with([-1, 604801]);

it('reads a ban', function (): void {
    $discord = new ClientFake([['user' => ['id' => '900', 'username' => 'spammer'], 'reason' => null]]);

    $ban = $discord->members()->getBan('42', '900');

    expect($ban->user->id)->toBe('900')->and($ban->reason)->toBeNull();
});

it('decodes a member without user, nick or join date', function (): void {
    $discord = new ClientFake([['roles' => []]]);

    $member = $discord->members()->get('42', '900');

    expect($member->user)->toBeNull()
        ->and($member->id())->toBeNull()
        ->and($member->displayName())->toBeNull()
        ->and($member->roleIds)->toBe([])
        ->and($member->joinedAt)->toBeNull();
});

it('rejects an empty id before calling discord', function (): void {
    $discord = new ClientFake;

    expect(fn () => $discord->members()->get('42', ''))->toThrow(InvalidArgumentException::class);
    $discord->assertNothingSent();
});
