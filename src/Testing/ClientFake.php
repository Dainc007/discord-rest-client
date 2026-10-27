<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Testing;

use AssertionError;
use Dainc007\DiscordRestClient\Contracts\ClientContract;
use Dainc007\DiscordRestClient\Resources\ChannelsResource;
use Dainc007\DiscordRestClient\Resources\GuildsResource;
use Dainc007\DiscordRestClient\Resources\MembersResource;
use Dainc007\DiscordRestClient\Resources\RolesResource;
use PHPUnit\Framework\Assert;

/**
 * Drop-in client for tests: no HTTP, records calls, returns canned responses.
 *
 *     $discord = new ClientFake([['id' => '1', 'name' => 'Admins']]);
 *     $discord->roles()->create('guild-1', 'Admins');
 *     $discord->assertSent('POST /guilds/{guild}/roles', fn (RecordedRequest $r) => $r->body['name'] === 'Admins');
 */
final class ClientFake implements ClientContract
{
    private readonly FakeTransport $transport;

    /**
     * @param  list<mixed>  $responses  See {@see FakeTransport} for accepted shapes
     */
    public function __construct(
        array $responses = [],
        private readonly bool $membersIntent = false,
    ) {
        $this->transport = new FakeTransport($responses);
    }

    public function addResponses(mixed ...$responses): self
    {
        $this->transport->addResponses(...$responses);

        return $this;
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

    /**
     * @return list<RecordedRequest>
     */
    public function recorded(): array
    {
        return $this->transport->recorded();
    }

    /**
     * @param  string|callable(RecordedRequest): bool  $routeKeyOrCallback  e.g. "DELETE /guilds/{guild}/bans/{user}"
     * @param  (callable(RecordedRequest): bool)|null  $callback
     * @return list<RecordedRequest>
     */
    public function sent(string|callable $routeKeyOrCallback, ?callable $callback = null): array
    {
        if (! is_string($routeKeyOrCallback)) {
            $callback = $routeKeyOrCallback;
            $routeKeyOrCallback = null;
        }

        return array_values(array_filter(
            $this->transport->recorded(),
            static fn (RecordedRequest $request): bool => ($routeKeyOrCallback === null || $request->routeKey() === $routeKeyOrCallback)
                && ($callback === null || $callback($request) === true),
        ));
    }

    /**
     * @param  string|callable(RecordedRequest): bool  $routeKeyOrCallback
     * @param  (callable(RecordedRequest): bool)|null  $callback
     */
    public function assertSent(string|callable $routeKeyOrCallback, ?callable $callback = null): void
    {
        self::check(
            $this->sent($routeKeyOrCallback, $callback) !== [],
            'Expected Discord request was not sent'.(is_string($routeKeyOrCallback) ? ": {$routeKeyOrCallback}" : '.'),
        );
    }

    /**
     * @param  string|callable(RecordedRequest): bool  $routeKeyOrCallback
     * @param  (callable(RecordedRequest): bool)|null  $callback
     */
    public function assertNotSent(string|callable $routeKeyOrCallback, ?callable $callback = null): void
    {
        self::check(
            $this->sent($routeKeyOrCallback, $callback) === [],
            'Unexpected Discord request was sent'.(is_string($routeKeyOrCallback) ? ": {$routeKeyOrCallback}" : '.'),
        );
    }

    public function assertSentCount(int $count): void
    {
        $actual = count($this->transport->recorded());

        self::check($actual === $count, "Expected {$count} Discord requests, {$actual} were sent.");
    }

    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    private static function check(bool $condition, string $message): void
    {
        if (class_exists(Assert::class)) {
            Assert::assertTrue($condition, $message);

            return;
        }

        if (! $condition) {
            throw new AssertionError($message);
        }
    }
}
