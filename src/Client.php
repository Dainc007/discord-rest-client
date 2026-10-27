<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient;

use Dainc007\DiscordRestClient\Contracts\BucketStore;
use Dainc007\DiscordRestClient\Contracts\ClientContract;
use Dainc007\DiscordRestClient\Contracts\Clock;
use Dainc007\DiscordRestClient\Contracts\Transport;
use Dainc007\DiscordRestClient\RateLimit\InMemoryBucketStore;
use Dainc007\DiscordRestClient\RateLimit\RateLimiter;
use Dainc007\DiscordRestClient\RateLimit\SystemClock;
use Dainc007\DiscordRestClient\Resources\ChannelsResource;
use Dainc007\DiscordRestClient\Resources\GuildsResource;
use Dainc007\DiscordRestClient\Resources\MembersResource;
use Dainc007\DiscordRestClient\Resources\RolesResource;
use Dainc007\DiscordRestClient\Transport\HttpTransport;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Entry point: a Discord REST API v10 client authenticated with a bot token.
 *
 *     $discord = Client::make($botToken);
 *     $discord->members()->addRole($guildId, $userId, $roleId, reason: 'Linked Discord account');
 */
final class Client implements ClientContract
{
    /** Sent in the User-Agent; bump it with every release tag. */
    public const string VERSION = '0.1.0';

    public function __construct(
        private readonly Transport $transport,
        private readonly bool $membersIntent = false,
    ) {}

    /**
     * Build a client. Any PSR-18 client and PSR-17 factory not passed in is
     * found with php-http/discovery (Guzzle, Symfony HttpClient, ...).
     *
     * @param  bool  $membersIntent  Only set true once "Server Members Intent" is on in the developer portal
     */
    public static function make(
        string $token,
        bool $membersIntent = false,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?BucketStore $bucketStore = null,
        ?Clock $clock = null,
        string $baseUri = HttpTransport::DEFAULT_BASE_URI,
        int $maxRetries = 3,
        float $maxRetryAfterSeconds = 60.0,
    ): self {
        $token = trim($token);

        if ($token === '') {
            throw new InvalidArgumentException('A Discord bot token is required.');
        }

        if (str_starts_with($token, 'Bot ')) {
            $token = substr($token, 4);
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must not be negative.');
        }

        $transport = new HttpTransport(
            httpClient: $httpClient ?? Psr18ClientDiscovery::find(),
            requestFactory: $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            streamFactory: $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            token: $token,
            rateLimiter: new RateLimiter($bucketStore ?? new InMemoryBucketStore, $clock ?? new SystemClock),
            baseUri: $baseUri,
            maxRetries: $maxRetries,
            maxRetryAfterSeconds: $maxRetryAfterSeconds,
        );

        return new self($transport, $membersIntent);
    }

    public function guilds(): GuildsResource
    {
        return new GuildsResource($this->transport);
    }

    public function channels(): ChannelsResource
    {
        return new ChannelsResource($this->transport);
    }

    public function roles(): RolesResource
    {
        return new RolesResource($this->transport);
    }

    public function members(): MembersResource
    {
        return new MembersResource($this->transport, $this->membersIntent);
    }
}
