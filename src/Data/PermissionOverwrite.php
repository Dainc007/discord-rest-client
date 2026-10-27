<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Data;

use Dainc007\DiscordRestClient\Enums\OverwriteType;
use Dainc007\DiscordRestClient\Enums\Permission;

/**
 * A channel permission overwrite for one role or one member.
 */
final readonly class PermissionOverwrite
{
    public function __construct(
        public string $id,
        public OverwriteType $type,
        public string $allow = '0',
        public string $deny = '0',
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id: Attributes::requiredString($attributes, 'id', 'permission overwrite'),
            type: OverwriteType::tryFrom(Attributes::int($attributes, 'type')) ?? OverwriteType::Role,
            allow: Attributes::string($attributes, 'allow') ?? '0',
            deny: Attributes::string($attributes, 'deny') ?? '0',
        );
    }

    /**
     * @param  list<Permission>  $allow
     * @param  list<Permission>  $deny
     */
    public static function forRole(string $roleId, array $allow = [], array $deny = []): self
    {
        return new self($roleId, OverwriteType::Role, Permission::combine(...$allow), Permission::combine(...$deny));
    }

    /**
     * @param  list<Permission>  $allow
     * @param  list<Permission>  $deny
     */
    public static function forMember(string $userId, array $allow = [], array $deny = []): self
    {
        return new self($userId, OverwriteType::Member, Permission::combine(...$allow), Permission::combine(...$deny));
    }

    /**
     * @return list<Permission>
     */
    public function allowed(): array
    {
        return Permission::fromBitfield($this->allow);
    }

    /**
     * @return list<Permission>
     */
    public function denied(): array
    {
        return Permission::fromBitfield($this->deny);
    }

    /**
     * @return array{id: string, type: int, allow: string, deny: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'allow' => $this->allow,
            'deny' => $this->deny,
        ];
    }
}
