<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Resources;

use Dainc007\DiscordRestClient\Data\Channel;
use Dainc007\DiscordRestClient\Data\Guild;
use Dainc007\DiscordRestClient\Data\Role;
use Dainc007\DiscordRestClient\Http\Route;

final class GuildsResource extends Resource
{
    /**
     * Read a guild, with approximate member and presence counts.
     */
    public function get(string $guildId, bool $withCounts = true): Guild
    {
        return Guild::fromArray($this->requestArray(
            Route::get('/guilds/{guild}', ['guild' => $guildId]),
            query: $withCounts ? ['with_counts' => true] : [],
        ));
    }

    /**
     * Every channel and category in the guild (threads excluded, as Discord returns them).
     *
     * @return list<Channel>
     */
    public function channels(string $guildId): array
    {
        return $this->mapList(
            $this->requestArray(Route::get('/guilds/{guild}/channels', ['guild' => $guildId])),
            Channel::fromArray(...),
        );
    }

    /**
     * Every role in the guild, including @everyone.
     *
     * @return list<Role>
     */
    public function roles(string $guildId): array
    {
        return $this->mapList(
            $this->requestArray(Route::get('/guilds/{guild}/roles', ['guild' => $guildId])),
            Role::fromArray(...),
        );
    }
}
