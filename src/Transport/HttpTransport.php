<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Transport;

use Dainc007\DiscordRestClient\Client;
use Dainc007\DiscordRestClient\Contracts\Transport;
use Dainc007\DiscordRestClient\Exceptions\DiscordApiException;
use Dainc007\DiscordRestClient\Exceptions\DiscordException;
use Dainc007\DiscordRestClient\Exceptions\InvalidTokenException;
use Dainc007\DiscordRestClient\Exceptions\MissingPermissionException;
use Dainc007\DiscordRestClient\Exceptions\RateLimitedException;
use Dainc007\DiscordRestClient\Exceptions\TransportException;
use Dainc007\DiscordRestClient\Exceptions\UnknownResourceException;
use Dainc007\DiscordRestClient\Http\Route;
use Dainc007\DiscordRestClient\RateLimit\RateLimiter;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use stdClass;

/**
 * Sends Discord API calls through a PSR-18 client.
 *
 * Handles authentication, the audit-log reason header, JSON encoding,
 * rate-limit waits, 429 retries and mapping errors to exceptions. 401 and 403
 * are never retried: too many of them get the IP banned by Cloudflare.
 */
final class HttpTransport implements Transport
{
    public const string DEFAULT_BASE_URI = 'https://discord.com/api/v10';

    /** Discord caps audit-log reasons at 512 characters. */
    public const int MAX_AUDIT_REASON_LENGTH = 512;

    /** First wait before retrying a read after a 5xx or a network error; doubles per attempt. Writes are never retried. */
    public const float RETRY_BACKOFF_SECONDS = 0.5;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $token,
        private readonly RateLimiter $rateLimiter = new RateLimiter,
        private readonly string $baseUri = self::DEFAULT_BASE_URI,
        private readonly int $maxRetries = 3,
        private readonly float $maxRetryAfterSeconds = 60.0,
        private readonly string $userAgent = 'DiscordBot (https://github.com/dainc007/discord-rest-client, '.Client::VERSION.')',
    ) {}

    public function send(Route $route, ?array $body = null, array $query = [], ?string $auditReason = null): mixed
    {
        $attempt = 0;

        while (true) {
            $this->rateLimiter->waitForAvailability($route);

            try {
                $response = $this->httpClient->sendRequest($this->buildRequest($route, $body, $query, $auditReason));
            } catch (ClientExceptionInterface $exception) {
                if ($exception instanceof NetworkExceptionInterface && $this->canRetryRead($route, $attempt)) {
                    $this->backOffBeforeRetry(++$attempt);

                    continue;
                }

                throw new TransportException('Discord request failed: '.$exception->getMessage(), previous: $exception);
            }

            $this->rateLimiter->recordResponse($route, $response);
            $status = $response->getStatusCode();

            if ($status === 429) {
                $payload = $this->decodeErrorPayload($response);
                $retryAfter = $this->retryAfterSeconds($response, $payload);
                $isGlobal = ($payload['global'] ?? false) === true
                    || strtolower($response->getHeaderLine('X-RateLimit-Global')) === 'true';

                $this->rateLimiter->recordRateLimited($route, $retryAfter, $isGlobal);

                if ($attempt >= $this->maxRetries || $retryAfter > $this->maxRetryAfterSeconds) {
                    throw new RateLimitedException(
                        message: sprintf('Discord rate limit hit on %s; retry after %.3f s.', $route->routeKey(), $retryAfter),
                        retryAfter: $retryAfter,
                        isGlobal: $isGlobal,
                        scope: $response->getHeaderLine('X-RateLimit-Scope') ?: null,
                        discordCode: isset($payload['code']) && is_int($payload['code']) ? $payload['code'] : null,
                    );
                }

                $attempt++;
                $this->rateLimiter->clock()->sleep($retryAfter);

                continue;
            }

            if ($status >= 200 && $status < 300) {
                return $this->decodeSuccess($response);
            }

            if ($status >= 500 && $this->canRetryRead($route, $attempt)) {
                $this->backOffBeforeRetry(++$attempt);

                continue;
            }

            throw $this->exceptionFor($route, $response);
        }
    }

    /**
     * @param  array<array-key, mixed>|null  $body
     * @param  array<string, scalar>  $query
     */
    private function buildRequest(Route $route, ?array $body, array $query, ?string $auditReason): RequestInterface
    {
        $uri = rtrim($this->baseUri, '/').$route->path();

        if ($query !== []) {
            $uri .= '?'.http_build_query(array_map(
                static fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value,
                $query,
            ), '', '&', PHP_QUERY_RFC3986);
        }

        $request = $this->requestFactory->createRequest($route->method, $uri)
            ->withHeader('Authorization', 'Bot '.$this->token)
            ->withHeader('User-Agent', $this->userAgent)
            ->withHeader('Accept', 'application/json');

        if ($auditReason !== null && trim($auditReason) !== '') {
            $request = $request->withHeader('X-Audit-Log-Reason', self::encodeAuditReason($auditReason));
        }

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(
                    json_encode($body === [] ? new stdClass : $body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ));
        }

        return $request;
    }

    /**
     * A write is never retried after a 5xx or a network error: Discord may already have applied it.
     */
    private function canRetryRead(Route $route, int $attempt): bool
    {
        return ! $route->isWrite() && $attempt < $this->maxRetries;
    }

    private function backOffBeforeRetry(int $attempt): void
    {
        $this->rateLimiter->clock()->sleep(self::RETRY_BACKOFF_SECONDS * 2 ** ($attempt - 1));
    }

    /**
     * Discord expects the reason URL-encoded so non-ASCII text survives the header.
     */
    public static function encodeAuditReason(string $reason): string
    {
        return rawurlencode(mb_substr(trim($reason), 0, self::MAX_AUDIT_REASON_LENGTH));
    }

    private function decodeSuccess(ResponseInterface $response): mixed
    {
        $contents = (string) $response->getBody();

        if ($response->getStatusCode() === 204 || trim($contents) === '') {
            return null;
        }

        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw new TransportException('Discord returned a body that is not valid JSON.', $response->getStatusCode(), previous: $exception);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeErrorPayload(ResponseInterface $response): array
    {
        $contents = (string) $response->getBody();

        if (trim($contents) === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function retryAfterSeconds(ResponseInterface $response, array $payload): float
    {
        if (isset($payload['retry_after']) && is_numeric($payload['retry_after'])) {
            return max(0.0, (float) $payload['retry_after']);
        }

        $header = $response->getHeaderLine('Retry-After');

        if ($header !== '' && is_numeric($header)) {
            return max(0.0, (float) $header);
        }

        $resetAfter = $response->getHeaderLine('X-RateLimit-Reset-After');

        return $resetAfter !== '' && is_numeric($resetAfter) ? max(0.0, (float) $resetAfter) : 1.0;
    }

    private function exceptionFor(Route $route, ResponseInterface $response): DiscordException
    {
        $payload = $this->decodeErrorPayload($response);
        $status = $response->getStatusCode();
        $code = isset($payload['code']) && is_int($payload['code']) ? $payload['code'] : null;
        $errors = isset($payload['errors']) && is_array($payload['errors']) ? $payload['errors'] : [];
        $discordMessage = isset($payload['message']) && is_string($payload['message']) ? $payload['message'] : $response->getReasonPhrase();
        $message = sprintf('Discord %d on %s %s: %s', $status, $route->method, $route->path(), $discordMessage);

        return match ($status) {
            401 => new InvalidTokenException($message, $status, $code, $errors),
            403 => new MissingPermissionException($message, $status, $code, $errors),
            404 => new UnknownResourceException($message, $status, $code, $errors),
            default => new DiscordApiException($message, $status, $code, $errors),
        };
    }
}
