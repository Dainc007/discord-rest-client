<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Data;

use Dainc007\DiscordRestClient\Enums\Permission;

/**
 * A guild role.
 */
final readonly class Role
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $color = 0,
        public bool $hoist = false,
        public int $position = 0,
        public string $permissions = '0',
        public bool $managed = false,
        public bool $mentionable = false,
        public ?string $icon = null,
        public ?string $unicodeEmoji = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id: Attributes::requiredString($attributes, 'id', 'role'),
            name: Attributes::requiredString($attributes, 'name', 'role'),
            color: Attributes::int($attributes, 'color'),
            hoist: Attributes::bool($attributes, 'hoist'),
            position: Attributes::int($attributes, 'position'),
            permissions: Attributes::string($attributes, 'permissions') ?? '0',
            managed: Attributes::bool($attributes, 'managed'),
            mentionable: Attributes::bool($attributes, 'mentionable'),
            icon: Attributes::string($attributes, 'icon'),
            unicodeEmoji: Attributes::string($attributes, 'unicode_emoji'),
            raw: $attributes,
        );
    }

    public function hasPermission(Permission $permission): bool
    {
        return $permission->isIn($this->permissions);
    }

    /**
     * Colour as "#rrggbb", or null when the role has no colour.
     */
    public function hexColor(): ?string
    {
        return $this->color === 0 ? null : sprintf('#%06x', $this->color);
    }
}
