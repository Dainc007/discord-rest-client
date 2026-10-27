# discord-rest-client

[![Tests](https://github.com/dainc007/discord-rest-client/actions/workflows/tests.yml/badge.svg)](https://github.com/dainc007/discord-rest-client/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/dainc007/discord-rest-client.svg)](https://packagist.org/packages/dainc007/discord-rest-client)
[![PHP version](https://img.shields.io/packagist/dependency-v/dainc007/discord-rest-client/php.svg)](https://packagist.org/packages/dainc007/discord-rest-client)

Framework-free PHP client for the Discord REST API v10, aimed at managing a server with a bot token: guilds, channels, roles and members. PSR-18 transport, Discord's rate limits handled for you, one exception per error kind, and a test fake.

## Install

```bash
composer require dainc007/discord-rest-client guzzlehttp/guzzle
```

Any PSR-18 client with PSR-17 factories works in place of Guzzle (Symfony HttpClient, for example). Pass them in, or let `php-http/discovery` find them. Requires PHP 8.3 or newer.

## What it covers

Managing one server with a bot token: reading the server, channels and their permissions, roles, and members (roles, nicknames, timeouts, kicks, bans). It does not send messages, and has no invites, server settings, ban lists, audit-log reading, webhooks or gateway (live events). Those may come later; for a long-running bot that reacts to events, use DiscordPHP.

## Setting up Discord

1. Open the [Discord developer portal](https://discord.com/developers/applications), click **New Application** and give it a name.
2. Under **Bot**, click **Reset Token** and copy the token. Discord shows it once. Keep it out of git; anyone with it controls the bot.
3. Only if you will list all members: under **Bot → Privileged Gateway Intents**, switch on **Server Members Intent** (see [Listing all members](#listing-all-members)).
4. Invite the bot: under **OAuth2 → URL Generator**, tick the `bot` scope, then only the permissions you need: **Manage Roles**, **Manage Channels**, **Manage Nicknames**, **Moderate Members** (timeouts), **Kick Members**, **Ban Members**. Open the generated URL and pick your server. You need the Manage Server permission there.
5. Get the server ID: in Discord, **User Settings → Advanced → Developer Mode** on, then right-click the server icon → **Copy Server ID**.
6. In **Server Settings → Roles**, drag the bot's role **above** every role it should give, take or edit. A bot can only change roles below its own highest role, and never the server owner. This is the usual cause of a 403 (`MissingPermissionException`, Discord code 50013).

## Usage

```php
use Dainc007\DiscordRestClient\Client;
use Dainc007\DiscordRestClient\Data\PermissionOverwrite;
use Dainc007\DiscordRestClient\Enums\ChannelType;
use Dainc007\DiscordRestClient\Enums\Permission;

$discord = Client::make($botToken);
// or: Client::make($botToken, httpClient: $psr18, requestFactory: $psr17, streamFactory: $psr17);

$guild    = $discord->guilds()->get($guildId);
$roles    = $discord->guilds()->roles($guildId);
$channels = $discord->guilds()->channels($guildId);

$role = $discord->roles()->create($guildId, 'Players', [Permission::ViewChannel], color: 0x3498DB, reason: 'Set up by my app');
$discord->members()->addRole($guildId, $userId, $role->id, reason: 'Linked Discord account');

$discord->channels()->create($guildId, 'Locker room', ChannelType::GuildVoice, permissionOverwrites: [
    PermissionOverwrite::forRole($guildId, deny: [Permission::ViewChannel]),   // @everyone
    PermissionOverwrite::forRole($role->id, allow: [Permission::ViewChannel, Permission::Connect]),
]);

$found = $discord->members()->search($guildId, 'anna');
```

| Resource | Methods |
|---|---|
| `guilds()` | `get`, `channels`, `roles` |
| `channels()` | `create`, `get`, `edit`, `delete`, `reorder`, `setPermissionOverwrite`, `deletePermissionOverwrite` |
| `roles()` | `create`, `edit`, `delete`, `reorder` (list them with `guilds()->roles()`) |
| `members()` | `get`, `search`, `list`, `all`, `addRole`, `removeRole`, `setNickname`, `timeout`, `kick`, `ban`, `unban`, `getBan` |

Results are readonly objects (`Guild`, `Channel`, `Role`, `Member`, `User`, `Ban`, `PermissionOverwrite`); each keeps Discord's full payload in `raw`. Permissions are the `Permission` bit-flag enum; Discord's decimal-string bitfields go through `Permission::combine()` and `Permission::fromBitfield()`.

### Audit reasons

Every write takes an optional `reason`, sent as `X-Audit-Log-Reason` (URL-encoded, cut at 512 characters), so Discord's own audit log shows why a change was made.

### Listing all members

`members()->list()` and `members()->all()` need the **Server Members** privileged intent (Discord developer portal → your application → Bot → Privileged Gateway Intents). They are off by default and throw `MembersIntentDisabledException` before any request. Once the intent is on in the portal:

```php
$discord = Client::make($botToken, membersIntent: true);

foreach ($discord->members()->all($guildId) as $member) { /* pages of 1000 */ }
```

Reading one member and searching by name work without it.

## Rate limits

- Per-route buckets from `X-RateLimit-Bucket`, tracked per guild/channel; the client waits when a bucket is empty.
- On a 429 it waits `retry_after` and retries. A wait longer than `maxRetryAfterSeconds` (60) throws `RateLimitedException` instead of blocking. A global 429 pauses every route.
- At most 50 requests a second per client. This count lives in memory, so it is not shared between processes even with a shared `BucketStore`; several workers together learn about the global limit only from Discord's 429.
- A read (GET) that gets a 5xx or a network error (a PSR-18 `NetworkExceptionInterface`: timeout, refused connection) is retried with a short, doubling wait (0.5 s, 1 s, 2 s). Writes are never retried on these, because the change may already have gone through.
- All retries of one call, 429s, 5xx and network errors together, share one budget: `maxRetries`, 3 by default. A 429 followed by a 502 uses two of the three.
- 401 and 403 are never retried (too many get the IP banned by Cloudflare).

Bucket state goes through the `BucketStore` interface, in memory by default; pass your own (for example backed by a shared cache) as `bucketStore:`.

## Errors

All extend `DiscordException`, which carries `httpStatus`, Discord's JSON error `discordCode` and the field `errors`.

| Exception | When |
|---|---|
| `InvalidTokenException` | 401 |
| `MissingPermissionException` | 403, e.g. code 50013, or a role above the bot's highest role |
| `UnknownResourceException` | 404, e.g. code 10011 Unknown Role |
| `RateLimitedException` | 429 after retries ran out (`retryAfter`, `isGlobal`, `scope`) |
| `DiscordApiException` | any other error, e.g. 400 code 50035 Invalid Form Body |
| `TransportException` | network failure, or a body that is not JSON |
| `MembersIntentDisabledException` | listing all members with the intent off |

## Testing your code

```php
use Dainc007\DiscordRestClient\Exceptions\MissingPermissionException;
use Dainc007\DiscordRestClient\Testing\ClientFake;
use Dainc007\DiscordRestClient\Testing\RecordedRequest;

$discord = new ClientFake([
    null,                                               // 204 for addRole
    ['id' => '1', 'name' => 'Admins'],                  // decoded JSON
    new MissingPermissionException('Missing Permissions', 403, 50013), // thrown
]);

// ... run code that receives $discord as a ClientContract ...

$discord->assertSent('PUT /guilds/{guild}/members/{user}/roles/{role}',
    fn (RecordedRequest $r) => $r->parameter('user') === '900' && $r->auditReason !== null);
$discord->assertSentCount(3);
```

Type-hint `Dainc007\DiscordRestClient\Contracts\ClientContract` so the real client and the fake are interchangeable.

## Package tests

```bash
composer test                                               # no network
DISCORD_BOT_TOKEN=... DISCORD_GUILD_ID=... composer test-live   # read-only live checks
```

## Changelog and security

See [CHANGELOG.md](CHANGELOG.md). To report a security problem, see [SECURITY.md](SECURITY.md).

## Licence

MIT. See [LICENSE.md](LICENSE.md).
