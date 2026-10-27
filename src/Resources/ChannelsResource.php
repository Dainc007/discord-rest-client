<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Resources;

use Dainc007\DiscordRestClient\Data\Channel;
use Dainc007\DiscordRestClient\Data\PermissionOverwrite;
use Dainc007\DiscordRestClient\Enums\ChannelType;
use Dainc007\DiscordRestClient\Http\Route;

final class ChannelsResource extends Resource
{
    /**
     * Create a channel or category in a guild.
     *
     * @param  array<string, mixed>  $attributes  Extra Discord fields: topic, parent_id, position, nsfw, ...
     * @param  list<PermissionOverwrite>  $permissionOverwrites
     */
    public function create(
        string $guildId,
        string $name,
        ChannelType $type = ChannelType::GuildText,
        array $attributes = [],
        array $permissionOverwrites = [],
        ?string $reason = null,
    ): Channel {
        $body = ['name' => $name, 'type' => $type->value] + $attributes;

        if ($permissionOverwrites !== []) {
            $body['permission_overwrites'] = array_map(
                static fn (PermissionOverwrite $overwrite): array => $overwrite->toArray(),
                $permissionOverwrites,
            );
        }

        return Channel::fromArray($this->requestArray(
            Route::post('/guilds/{guild}/channels', ['guild' => $guildId]),
            $body,
            auditReason: $reason,
        ));
    }

    public function get(string $channelId): Channel
    {
        return Channel::fromArray($this->requestArray(Route::get('/channels/{channel}', ['channel' => $channelId])));
    }

    /**
     * @param  array<string, mixed>  $attributes  Discord fields to change: name, topic, parent_id, ...
     */
    public function edit(string $channelId, array $attributes, ?string $reason = null): Channel
    {
        return Channel::fromArray($this->requestArray(
            Route::patch('/channels/{channel}', ['channel' => $channelId]),
            $attributes,
            auditReason: $reason,
        ));
    }

    /**
     * Delete a channel, or a category (its children are kept, without a parent).
     */
    public function delete(string $channelId, ?string $reason = null): Channel
    {
        return Channel::fromArray($this->requestArray(
            Route::delete('/channels/{channel}', ['channel' => $channelId]),
            auditReason: $reason,
        ));
    }

    /**
     * Change channel positions in the guild's list, and optionally move
     * channels to another category (null parent = no category).
     *
     * @param  array<string, int>  $positions  channel id => position
     * @param  array<string, string|null>  $parents  channel id => new category id
     */
    public function reorder(string $guildId, array $positions, ?string $reason = null, array $parents = []): void
    {
        $body = $this->positionsBody($positions);

        foreach ($body as $index => $item) {
            if (array_key_exists($item['id'], $parents)) {
                $body[$index]['parent_id'] = $parents[$item['id']];
            }
        }

        $this->requestNothing(
            Route::patch('/guilds/{guild}/channels', ['guild' => $guildId]),
            $body,
            $reason,
        );
    }

    /**
     * Create or replace the permission overwrite for one role or member.
     */
    public function setPermissionOverwrite(string $channelId, PermissionOverwrite $overwrite, ?string $reason = null): void
    {
        $this->requestNothing(
            Route::put('/channels/{channel}/permissions/{overwrite}', ['channel' => $channelId, 'overwrite' => $overwrite->id]),
            ['allow' => $overwrite->allow, 'deny' => $overwrite->deny, 'type' => $overwrite->type->value],
            $reason,
        );
    }

    /**
     * Remove the overwrite for a role or member id.
     */
    public function deletePermissionOverwrite(string $channelId, string $roleOrUserId, ?string $reason = null): void
    {
        $this->requestNothing(
            Route::delete('/channels/{channel}/permissions/{overwrite}', ['channel' => $channelId, 'overwrite' => $roleOrUserId]),
            auditReason: $reason,
        );
    }
}
