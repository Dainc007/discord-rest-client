<?php

declare(strict_types=1);

use Dainc007\DiscordRestClient\Data\PermissionOverwrite;
use Dainc007\DiscordRestClient\Enums\ChannelType;
use Dainc007\DiscordRestClient\Enums\OverwriteType;
use Dainc007\DiscordRestClient\Enums\Permission;
use Dainc007\DiscordRestClient\Exceptions\DiscordApiException;
use Dainc007\DiscordRestClient\Testing\ClientFake;
use Dainc007\DiscordRestClient\Testing\RecordedRequest;

it('reads a guild with counts', function (): void {
    $discord = new ClientFake([[
        'id' => '42', 'name' => 'My Server', 'owner_id' => '1', 'icon' => null,
        'approximate_member_count' => 120, 'features' => ['COMMUNITY'],
    ]]);

    $guild = $discord->guilds()->get('42');

    expect($guild->name)->toBe('My Server')
        ->and($guild->approximateMemberCount)->toBe(120)
        ->and($guild->approximatePresenceCount)->toBeNull()
        ->and($guild->features)->toBe(['COMMUNITY']);
    $discord->assertSent('GET /guilds/{guild}', fn (RecordedRequest $r): bool => $r->query === ['with_counts' => true]);
});

it('fails loudly when a required guild field is missing', function (): void {
    $discord = new ClientFake([['id' => '42']]);

    expect(fn () => $discord->guilds()->get('42'))->toThrow(DiscordApiException::class, '[name]');
});

it('lists channels with their overwrites, and an empty guild as an empty list', function (): void {
    $discord = new ClientFake([
        [
            ['id' => '10', 'type' => 4, 'name' => 'Liga', 'position' => 0],
            ['id' => '11', 'type' => 0, 'name' => 'ogólny', 'parent_id' => '10', 'permission_overwrites' => [
                ['id' => '111', 'type' => 0, 'allow' => '1024', 'deny' => '2048'],
            ]],
            ['id' => '12', 'type' => 99, 'name' => 'future type'],
        ],
        [],
    ]);

    $channels = $discord->guilds()->channels('42');

    expect($channels)->toHaveCount(3)
        ->and($channels[0]->isCategory())->toBeTrue()
        ->and($channels[1]->parentId)->toBe('10')
        ->and($channels[1]->name)->toBe('ogólny')
        ->and($channels[1]->permissionOverwrites[0]->allowed())->toBe([Permission::ViewChannel])
        ->and($channels[1]->permissionOverwrites[0]->denied())->toBe([Permission::SendMessages])
        ->and($channels[2]->channelType())->toBeNull()
        ->and($discord->guilds()->channels('43'))->toBe([]);
});

it('lists roles', function (): void {
    $discord = new ClientFake([[rolePayload(), rolePayload(['id' => '42', 'name' => '@everyone', 'color' => 0, 'position' => 0])]]);

    $roles = $discord->guilds()->roles('42');

    expect($roles[0]->hexColor())->toBe('#3498db')
        ->and($roles[0]->hasPermission(Permission::ManageRoles))->toBeTrue()
        ->and($roles[1]->hexColor())->toBeNull();
});

it('creates a channel with overwrites and a reason', function (): void {
    $discord = new ClientFake([['id' => '20', 'type' => 2, 'name' => 'Szatnia', 'guild_id' => '42']]);

    $channel = $discord->channels()->create(
        '42',
        'Szatnia',
        ChannelType::GuildVoice,
        ['parent_id' => '10'],
        [PermissionOverwrite::forRole('42', deny: [Permission::ViewChannel]), PermissionOverwrite::forMember('900', allow: [Permission::ViewChannel, Permission::Connect])],
        reason: 'Team channel',
    );

    expect($channel->channelType())->toBe(ChannelType::GuildVoice);
    $discord->assertSent('POST /guilds/{guild}/channels', fn (RecordedRequest $r): bool => $r->auditReason === 'Team channel' && $r->body === [
        'name' => 'Szatnia',
        'type' => 2,
        'parent_id' => '10',
        'permission_overwrites' => [
            ['id' => '42', 'type' => 0, 'allow' => '0', 'deny' => '1024'],
            ['id' => '900', 'type' => 1, 'allow' => (string) (1024 | (1 << 20)), 'deny' => '0'],
        ],
    ]);
});

it('reads one channel', function (): void {
    $discord = new ClientFake([['id' => '20', 'type' => 2, 'name' => 'Szatnia', 'parent_id' => '10']]);

    $channel = $discord->channels()->get('20');

    expect($channel->name)->toBe('Szatnia')
        ->and($channel->type)->toBe(ChannelType::GuildVoice->value)
        ->and($channel->parentId)->toBe('10');
    $discord->assertSent('GET /channels/{channel}', fn (RecordedRequest $r): bool => $r->path() === '/channels/20');
});

it('edits and deletes a channel', function (): void {
    $discord = new ClientFake([['id' => '20', 'type' => 0, 'name' => 'nowa'], ['id' => '20', 'type' => 0, 'name' => 'nowa']]);

    expect($discord->channels()->edit('20', ['name' => 'nowa'], 'rename')->name)->toBe('nowa');
    $discord->channels()->delete('20', 'gone');

    $discord->assertSent('PATCH /channels/{channel}', fn (RecordedRequest $r): bool => $r->body === ['name' => 'nowa'] && $r->auditReason === 'rename');
    $discord->assertSent('DELETE /channels/{channel}', fn (RecordedRequest $r): bool => $r->auditReason === 'gone');
});

it('reorders channels', function (): void {
    $discord = new ClientFake([null]);

    $discord->channels()->reorder('42', ['10' => 0, '11' => 1]);

    $discord->assertSent('PATCH /guilds/{guild}/channels', fn (RecordedRequest $r): bool => $r->body === [['id' => '10', 'position' => 0], ['id' => '11', 'position' => 1]]);
});

it('moves channels to another category while reordering', function (): void {
    $discord = new ClientFake([null]);

    $discord->channels()->reorder('42', ['10' => 0, '11' => 1], parents: ['10' => '99', '11' => null]);

    $discord->assertSent('PATCH /guilds/{guild}/channels', fn (RecordedRequest $r): bool => $r->body === [
        ['id' => '10', 'position' => 0, 'parent_id' => '99'],
        ['id' => '11', 'position' => 1, 'parent_id' => null],
    ]);
});

it('rejects an empty or negative reorder', function (array $positions): void {
    $discord = new ClientFake;

    expect(fn () => $discord->channels()->reorder('42', $positions))->toThrow(InvalidArgumentException::class);
    expect(fn () => $discord->roles()->reorder('42', $positions))->toThrow(InvalidArgumentException::class);
    $discord->assertNothingSent();
})->with([[[]], [['10' => -1]]]);

it('sets and removes a permission overwrite per role and per member', function (): void {
    $discord = new ClientFake([null, null, null]);

    $discord->channels()->setPermissionOverwrite('20', PermissionOverwrite::forRole('111', allow: [Permission::ViewChannel]), 'staff only');
    $discord->channels()->setPermissionOverwrite('20', PermissionOverwrite::forMember('900', deny: [Permission::SendMessages]));
    $discord->channels()->deletePermissionOverwrite('20', '111', 'reset');

    $sets = $discord->sent('PUT /channels/{channel}/permissions/{overwrite}');
    expect($sets)->toHaveCount(2)
        ->and($sets[0]->body)->toBe(['allow' => '1024', 'deny' => '0', 'type' => OverwriteType::Role->value])
        ->and($sets[0]->auditReason)->toBe('staff only')
        ->and($sets[1]->parameter('overwrite'))->toBe('900')
        ->and($sets[1]->body['type'])->toBe(OverwriteType::Member->value);
    $discord->assertSent('DELETE /channels/{channel}/permissions/{overwrite}', fn (RecordedRequest $r): bool => $r->auditReason === 'reset');
});

it('creates a role with permissions, colour and reason', function (): void {
    $discord = new ClientFake([rolePayload()]);

    $discord->roles()->create('42', 'Admins', [Permission::ManageRoles, Permission::KickMembers], color: 0x3498DB, hoist: true, reason: 'setup');

    $discord->assertSent('POST /guilds/{guild}/roles', fn (RecordedRequest $r): bool => $r->body['permissions'] === (string) ((1 << 28) | (1 << 1))
        && $r->body['color'] === 0x3498DB
        && $r->auditReason === 'setup');
});

it('leaves permissions out of a new role when none are given', function (): void {
    $discord = new ClientFake([rolePayload()]);

    $discord->roles()->create('42', 'Players');

    expect($discord->recorded()[0]->body)->not->toHaveKey('permissions');
});

it('creates a role with an empty permission list as zero', function (): void {
    $discord = new ClientFake([rolePayload()]);

    $discord->roles()->create('42', 'Muted', []);

    expect($discord->recorded()[0]->body['permissions'])->toBe('0');
});

it('accepts colour 0 and 0xFFFFFF and rejects values outside', function (int $color, bool $valid): void {
    $discord = new ClientFake([rolePayload()]);

    if ($valid) {
        $discord->roles()->create('42', 'x', color: $color);
        $discord->assertSentCount(1);
    } else {
        expect(fn () => $discord->roles()->create('42', 'x', color: $color))->toThrow(InvalidArgumentException::class);
        $discord->assertNothingSent();
    }
})->with([[0, true], [0xFFFFFF, true], [-1, false], [0x1000000, false]]);

it('edits a role, combining a permission list into a bitfield', function (): void {
    $discord = new ClientFake([rolePayload(['name' => 'Zarząd'])]);

    $role = $discord->roles()->edit('42', '111', ['name' => 'Zarząd', 'permissions' => [Permission::ViewChannel]], 'rename');

    expect($role->name)->toBe('Zarząd');
    $discord->assertSent('PATCH /guilds/{guild}/roles/{role}', fn (RecordedRequest $r): bool => $r->body === ['name' => 'Zarząd', 'permissions' => '1024'] && $r->auditReason === 'rename');
});

it('rejects a permission list that holds something other than Permission cases', function (): void {
    $discord = new ClientFake;

    expect(fn () => $discord->roles()->edit('42', '111', ['permissions' => ['ViewChannel']]))
        ->toThrow(InvalidArgumentException::class, 'list of Permission cases');
    $discord->assertNothingSent();
});

it('deletes and reorders roles', function (): void {
    $discord = new ClientFake([null, [rolePayload(['position' => 1]), rolePayload(['id' => '112', 'position' => 2])]]);

    $discord->roles()->delete('42', '111', 'unused');
    $roles = $discord->roles()->reorder('42', ['111' => 1, '112' => 2], 'tidy');

    expect($roles)->toHaveCount(2);
    $discord->assertSent('DELETE /guilds/{guild}/roles/{role}', fn (RecordedRequest $r): bool => $r->auditReason === 'unused');
    $discord->assertSent('PATCH /guilds/{guild}/roles', fn (RecordedRequest $r): bool => $r->auditReason === 'tidy');
});
