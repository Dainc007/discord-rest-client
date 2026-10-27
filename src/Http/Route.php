<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Http;

use InvalidArgumentException;

/**
 * One Discord endpoint call: HTTP method, path template and its parameters.
 *
 * The template (e.g. "/guilds/{guild}/roles/{role}") identifies the route for
 * rate-limit bucketing; Discord's "major parameters" (guild, channel, webhook)
 * split one bucket into separate limits per resource.
 */
final readonly class Route
{
    /** @var list<string> */
    private const array MAJOR_PARAMETERS = ['guild', 'channel', 'webhook'];

    /**
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        public string $method,
        public string $template,
        public array $parameters = [],
    ) {
        foreach ($parameters as $name => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Route parameter [{$name}] must not be empty.");
            }
        }
    }

    /**
     * @param  array<string, string>  $parameters
     */
    public static function get(string $template, array $parameters = []): self
    {
        return new self('GET', $template, $parameters);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    public static function post(string $template, array $parameters = []): self
    {
        return new self('POST', $template, $parameters);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    public static function patch(string $template, array $parameters = []): self
    {
        return new self('PATCH', $template, $parameters);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    public static function put(string $template, array $parameters = []): self
    {
        return new self('PUT', $template, $parameters);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    public static function delete(string $template, array $parameters = []): self
    {
        return new self('DELETE', $template, $parameters);
    }

    /**
     * The concrete path with every placeholder URL-encoded and filled in.
     */
    public function path(): string
    {
        $path = preg_replace_callback(
            '/\{(\w+)\}/',
            function (array $match): string {
                if (! array_key_exists($match[1], $this->parameters)) {
                    throw new InvalidArgumentException("Missing route parameter [{$match[1]}] for {$this->template}.");
                }

                return rawurlencode($this->parameters[$match[1]]);
            },
            $this->template,
        );

        return (string) $path;
    }

    /**
     * Key that identifies this route for bucket discovery, e.g. "PATCH /guilds/{guild}/roles/{role}".
     */
    public function routeKey(): string
    {
        return $this->method.' '.$this->template;
    }

    /**
     * The values of Discord's major parameters, joined, so buckets are tracked per guild/channel.
     */
    public function majorParameterKey(): string
    {
        $values = [];

        foreach (self::MAJOR_PARAMETERS as $name) {
            if (array_key_exists($name, $this->parameters)) {
                $values[] = $name.'='.$this->parameters[$name];
            }
        }

        return implode('&', $values);
    }

    public function isWrite(): bool
    {
        return $this->method !== 'GET';
    }
}
