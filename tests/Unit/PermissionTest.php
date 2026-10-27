<?php

declare(strict_types=1);

use Dainc007\DiscordRestClient\Enums\Permission;

it('combines flags into a decimal string', function (): void {
    expect(Permission::combine())->toBe('0')
        ->and(Permission::combine(Permission::Administrator))->toBe('8')
        ->and(Permission::combine(Permission::ViewChannel, Permission::ViewChannel))->toBe('1024');
});

it('reads flags from a bitfield, ignoring unknown bits', function (): void {
    $bitfield = (string) (Permission::KickMembers->value | Permission::SendPolls->value | (1 << 60));

    expect(Permission::fromBitfield($bitfield))->toBe([Permission::KickMembers, Permission::SendPolls])
        ->and(Permission::fromBitfield('0'))->toBe([])
        ->and(Permission::UseExternalApps->isIn($bitfield))->toBeFalse()
        ->and(Permission::SendPolls->isIn($bitfield))->toBeTrue();
});

it('round-trips every flag', function (): void {
    $all = Permission::combine(...Permission::cases());

    expect(Permission::fromBitfield($all))->toBe(Permission::cases());
});

it('rejects a bitfield that is not a non-negative integer string', function (string $bitfield): void {
    Permission::fromBitfield($bitfield);
})->with(['', '-1', 'abc', '1.5'])->throws(InvalidArgumentException::class);
