# discord-rest-client: rules for agents

A framework-free PHP client for the Discord REST API v10. It is the first of three packages: this client, a Laravel package and a Filament plugin built on top of it.

## Versions and releases

- Follow Semantic Versioning (semver.org). Tag releases as `vMAJOR.MINOR.PATCH`, for example `v0.1.0`. Packagist reads versions from git tags.
- Stay on 0.x until the Laravel package and the Filament plugin use the client and the API has settled. SemVer says that in 0.x anything may change at any time. Start at `0.1.0`.
- Composer reads `^0.1` as `>=0.1.0 <0.2.0`. In 0.x, a breaking change goes in a new minor version (`0.2.0`), never in a patch.
- From 1.0.0 on, a breaking change needs a new major version, a new feature a new minor, and a fix a new patch.
- Never move or delete a tag that has been pushed. Release a new version instead.
- Agents prepare commits and tags locally and stop there. Daniel reviews, then pushes.

## What becomes public

- Everything in `composer.json` (the `authors` emails too) and the author name and email of every commit is public once pushed. Packagist shows the `composer.json` data.
- The author email stays as it is in `composer.json` and in commits (decided 2026-10-27). Don't swap it for a noreply address.
- Don't put real server data, tokens or guild IDs in `src`, the README or the tests. The live test reads `DISCORD_BOT_TOKEN` and `DISCORD_GUILD_ID` from the environment only.
- No `Co-Authored-By` trailer in commits.

## Before a release

- `composer test`, `composer analyse` (PHPStan, level max) and `vendor/bin/pint --test` pass in this repo on its own.
- `Client::VERSION` matches the new tag; it goes into the User-Agent Discord sees.
- `CHANGELOG.md` has a section for the new version, dated the day of the tag: `## [0.1.0] - YYYY-MM-DD`.
- The README still lists every public method and what is out of scope.
