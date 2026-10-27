<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Data;

use DateTimeImmutable;
use Exception;

/**
 * A user's membership in one guild.
 */
final readonly class Member
{
    /**
     * @param  list<string>  $roleIds
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public ?User $user,
        public ?string $nick = null,
        public array $roleIds = [],
        public ?DateTimeImmutable $joinedAt = null,
        public bool $pending = false,
        public ?DateTimeImmutable $communicationDisabledUntil = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $user = Attributes::array($attributes, 'user');

        return new self(
            user: $user === [] ? null : User::fromArray($user),
            nick: Attributes::string($attributes, 'nick'),
            roleIds: Attributes::stringList($attributes, 'roles'),
            joinedAt: self::date(Attributes::string($attributes, 'joined_at')),
            pending: Attributes::bool($attributes, 'pending'),
            communicationDisabledUntil: self::date(Attributes::string($attributes, 'communication_disabled_until')),
            raw: $attributes,
        );
    }

    public function id(): ?string
    {
        return $this->user?->id;
    }

    /**
     * Nickname in this guild, else the user's display name, else null.
     */
    public function displayName(): ?string
    {
        return $this->nick ?? $this->user?->displayName();
    }

    public function hasRole(string $roleId): bool
    {
        return in_array($roleId, $this->roleIds, true);
    }

    private static function date(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
