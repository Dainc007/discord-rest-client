<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Data;

/**
 * A Discord server.
 */
final readonly class Guild
{
    /**
     * @param  list<string>  $features
     * @param  array<array-key, mixed>  $raw  The full payload, for fields not mapped here
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $ownerId,
        public ?string $icon = null,
        public ?string $description = null,
        public ?int $approximateMemberCount = null,
        public ?int $approximatePresenceCount = null,
        public array $features = [],
        public array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id: Attributes::requiredString($attributes, 'id', 'guild'),
            name: Attributes::requiredString($attributes, 'name', 'guild'),
            ownerId: Attributes::string($attributes, 'owner_id') ?? '',
            icon: Attributes::string($attributes, 'icon'),
            description: Attributes::string($attributes, 'description'),
            approximateMemberCount: Attributes::nullableInt($attributes, 'approximate_member_count'),
            approximatePresenceCount: Attributes::nullableInt($attributes, 'approximate_presence_count'),
            features: Attributes::stringList($attributes, 'features'),
            raw: $attributes,
        );
    }
}
