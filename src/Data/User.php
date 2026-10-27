<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Data;

/**
 * The Discord account behind a guild member.
 */
final readonly class User
{
    public function __construct(
        public string $id,
        public string $username,
        public ?string $globalName = null,
        public ?string $avatar = null,
        public bool $bot = false,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id: Attributes::requiredString($attributes, 'id', 'user'),
            username: Attributes::string($attributes, 'username') ?? '',
            globalName: Attributes::string($attributes, 'global_name'),
            avatar: Attributes::string($attributes, 'avatar'),
            bot: Attributes::bool($attributes, 'bot'),
        );
    }

    /**
     * The name Discord shows: global display name if set, else username.
     */
    public function displayName(): string
    {
        return $this->globalName ?? $this->username;
    }
}
