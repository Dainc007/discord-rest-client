<?php

declare(strict_types=1);

use Dainc007\DiscordRestClient\Client;

/*
 * Opt-in checks against the real Discord API. Excluded by default; run with
 *   DISCORD_BOT_TOKEN=... DISCORD_GUILD_ID=... composer test-live
 * Read-only: nothing here changes the server.
 */

beforeEach(function (): void {
    if (! getenv('DISCORD_BOT_TOKEN') || ! getenv('DISCORD_GUILD_ID')) {
        $this->markTestSkipped('Set DISCORD_BOT_TOKEN and DISCORD_GUILD_ID to run live tests.');
    }
});

it('reads the live guild, its channels and roles', function (): void {
    $discord = Client::make((string) getenv('DISCORD_BOT_TOKEN'));
    $guildId = (string) getenv('DISCORD_GUILD_ID');

    expect($discord->guilds()->get($guildId)->id)->toBe($guildId)
        ->and($discord->guilds()->roles($guildId))->not->toBeEmpty()
        ->and($discord->guilds()->channels($guildId))->toBeArray();
})->group('live');
