<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Resources;

use Dainc007\DiscordRestClient\Contracts\Transport;
use Dainc007\DiscordRestClient\Data\Ban;
use Dainc007\DiscordRestClient\Data\Member;
use Dainc007\DiscordRestClient\Exceptions\MembersIntentDisabledException;
use Dainc007\DiscordRestClient\Http\Route;
use DateTimeInterface;
use Generator;
use InvalidArgumentException;

/**
 * Guild members, bans and member roles.
 */
final class MembersResource extends Resource
{
    /** Discord's page size cap for member list and search. */
    public const int MAX_PAGE_SIZE = 1000;

    /** Discord's cap for deleting a banned user's message history: 7 days. */
    public const int MAX_DELETE_MESSAGE_SECONDS = 604800;

    /** Discord's longest timeout: 28 days. */
    public const int MAX_TIMEOUT_SECONDS = 2419200;

    public function __construct(
        Transport $transport,
        private readonly bool $membersIntent = false,
    ) {
        parent::__construct($transport);
    }

    public function get(string $guildId, string $userId): Member
    {
        return Member::fromArray($this->requestArray(
            Route::get('/guilds/{guild}/members/{user}', ['guild' => $guildId, 'user' => $userId]),
        ));
    }

    /**
     * Members whose username or nickname starts with the query.
     *
     * @return list<Member>
     */
    public function search(string $guildId, string $query, int $limit = 25): array
    {
        if (trim($query) === '') {
            throw new InvalidArgumentException('Member search query must not be empty.');
        }

        $this->assertPageSize($limit);

        return $this->mapList(
            $this->requestArray(
                Route::get('/guilds/{guild}/members/search', ['guild' => $guildId]),
                query: ['query' => $query, 'limit' => $limit],
            ),
            Member::fromArray(...),
        );
    }

    /**
     * One page of every member, ordered by user id.
     *
     * Needs the "Server Members" privileged intent; throws before any request when the client was built without it.
     *
     * @return list<Member>
     */
    public function list(string $guildId, int $limit = self::MAX_PAGE_SIZE, ?string $after = null): array
    {
        $this->assertMembersIntent();
        $this->assertPageSize($limit);

        $query = ['limit' => $limit];

        if ($after !== null) {
            $query['after'] = $after;
        }

        return $this->mapList(
            $this->requestArray(Route::get('/guilds/{guild}/members', ['guild' => $guildId]), query: $query),
            Member::fromArray(...),
        );
    }

    /**
     * Every member, fetched page by page.
     *
     * @return Generator<int, Member>
     */
    public function all(string $guildId, int $pageSize = self::MAX_PAGE_SIZE): Generator
    {
        $this->assertMembersIntent();
        $after = null;

        do {
            $page = $this->list($guildId, $pageSize, $after);

            foreach ($page as $member) {
                yield $member;
            }

            $last = $page === [] ? null : $page[array_key_last($page)]->id();
            $after = $last;
        } while (count($page) === $pageSize && $last !== null);
    }

    public function addRole(string $guildId, string $userId, string $roleId, ?string $reason = null): void
    {
        $this->requestNothing(
            Route::put('/guilds/{guild}/members/{user}/roles/{role}', ['guild' => $guildId, 'user' => $userId, 'role' => $roleId]),
            auditReason: $reason,
        );
    }

    public function removeRole(string $guildId, string $userId, string $roleId, ?string $reason = null): void
    {
        $this->requestNothing(
            Route::delete('/guilds/{guild}/members/{user}/roles/{role}', ['guild' => $guildId, 'user' => $userId, 'role' => $roleId]),
            auditReason: $reason,
        );
    }

    /**
     * Set a member's nickname; null or an empty string clears it.
     */
    public function setNickname(string $guildId, string $userId, ?string $nickname, ?string $reason = null): Member
    {
        if ($nickname !== null && mb_strlen($nickname) > 32) {
            throw new InvalidArgumentException('Discord nicknames are at most 32 characters.');
        }

        return Member::fromArray($this->requestArray(
            Route::patch('/guilds/{guild}/members/{user}', ['guild' => $guildId, 'user' => $userId]),
            ['nick' => $nickname === '' ? null : $nickname],
            auditReason: $reason,
        ));
    }

    /**
     * Time a member out until the given moment (at most 28 days ahead), or
     * lift a timeout with null. A timed-out member can read but cannot
     * write, react, speak or join voice. Needs "Moderate Members".
     */
    public function timeout(string $guildId, string $userId, ?DateTimeInterface $until, ?string $reason = null): Member
    {
        if ($until !== null && $until->getTimestamp() > time() + self::MAX_TIMEOUT_SECONDS) {
            throw new InvalidArgumentException('Discord timeouts last at most 28 days.');
        }

        return Member::fromArray($this->requestArray(
            Route::patch('/guilds/{guild}/members/{user}', ['guild' => $guildId, 'user' => $userId]),
            ['communication_disabled_until' => $until?->format(DateTimeInterface::ATOM)],
            auditReason: $reason,
        ));
    }

    /**
     * Remove a member from the guild. They can rejoin with an invite.
     */
    public function kick(string $guildId, string $userId, ?string $reason = null): void
    {
        $this->requestNothing(
            Route::delete('/guilds/{guild}/members/{user}', ['guild' => $guildId, 'user' => $userId]),
            auditReason: $reason,
        );
    }

    /**
     * Ban a user, optionally deleting up to 7 days of their messages.
     */
    public function ban(string $guildId, string $userId, int $deleteMessageSeconds = 0, ?string $reason = null): void
    {
        if ($deleteMessageSeconds < 0 || $deleteMessageSeconds > self::MAX_DELETE_MESSAGE_SECONDS) {
            throw new InvalidArgumentException('deleteMessageSeconds must be between 0 and '.self::MAX_DELETE_MESSAGE_SECONDS.'.');
        }

        $this->requestNothing(
            Route::put('/guilds/{guild}/bans/{user}', ['guild' => $guildId, 'user' => $userId]),
            $deleteMessageSeconds > 0 ? ['delete_message_seconds' => $deleteMessageSeconds] : [],
            $reason,
        );
    }

    public function unban(string $guildId, string $userId, ?string $reason = null): void
    {
        $this->requestNothing(
            Route::delete('/guilds/{guild}/bans/{user}', ['guild' => $guildId, 'user' => $userId]),
            auditReason: $reason,
        );
    }

    public function getBan(string $guildId, string $userId): Ban
    {
        return Ban::fromArray($this->requestArray(
            Route::get('/guilds/{guild}/bans/{user}', ['guild' => $guildId, 'user' => $userId]),
        ));
    }

    public function membersIntentEnabled(): bool
    {
        return $this->membersIntent;
    }

    private function assertMembersIntent(): void
    {
        if (! $this->membersIntent) {
            throw MembersIntentDisabledException::make();
        }
    }

    private function assertPageSize(int $limit): void
    {
        if ($limit < 1 || $limit > self::MAX_PAGE_SIZE) {
            throw new InvalidArgumentException('Limit must be between 1 and '.self::MAX_PAGE_SIZE.'.');
        }
    }
}
