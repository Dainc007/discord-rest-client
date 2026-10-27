<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Data;

use Dainc007\DiscordRestClient\Enums\ChannelType;

/**
 * A guild channel or category.
 *
 * `type` is kept as Discord's integer so channel types added later do not
 * break decoding; {@see self::channelType()} maps it to the enum when known.
 */
final readonly class Channel
{
    /**
     * @param  list<PermissionOverwrite>  $permissionOverwrites
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public int $type,
        public ?string $guildId = null,
        public ?string $name = null,
        public int $position = 0,
        public ?string $parentId = null,
        public ?string $topic = null,
        public bool $nsfw = false,
        public array $permissionOverwrites = [],
        public array $raw = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $overwrites = [];

        foreach (Attributes::array($attributes, 'permission_overwrites') as $overwrite) {
            if (is_array($overwrite)) {
                $overwrites[] = PermissionOverwrite::fromArray($overwrite);
            }
        }

        return new self(
            id: Attributes::requiredString($attributes, 'id', 'channel'),
            type: Attributes::int($attributes, 'type'),
            guildId: Attributes::string($attributes, 'guild_id'),
            name: Attributes::string($attributes, 'name'),
            position: Attributes::int($attributes, 'position'),
            parentId: Attributes::string($attributes, 'parent_id'),
            topic: Attributes::string($attributes, 'topic'),
            nsfw: Attributes::bool($attributes, 'nsfw'),
            permissionOverwrites: $overwrites,
            raw: $attributes,
        );
    }

    public function channelType(): ?ChannelType
    {
        return ChannelType::tryFrom($this->type);
    }

    public function isCategory(): bool
    {
        return $this->type === ChannelType::GuildCategory->value;
    }
}
