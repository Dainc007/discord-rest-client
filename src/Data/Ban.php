<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Data;

/**
 * A ban entry: the banned user and the reason given, if any.
 */
final readonly class Ban
{
    public function __construct(
        public User $user,
        public ?string $reason = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            user: User::fromArray(Attributes::array($attributes, 'user')),
            reason: Attributes::string($attributes, 'reason'),
        );
    }
}
