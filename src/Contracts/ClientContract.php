<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Contracts;

use Dainc007\DiscordRestClient\Resources\ChannelsResource;
use Dainc007\DiscordRestClient\Resources\GuildsResource;
use Dainc007\DiscordRestClient\Resources\MembersResource;
use Dainc007\DiscordRestClient\Resources\RolesResource;

/**
 * What both the real client and the test fake expose.
 */
interface ClientContract
{
    public function guilds(): GuildsResource;

    public function channels(): ChannelsResource;

    public function roles(): RolesResource;

    public function members(): MembersResource;
}
