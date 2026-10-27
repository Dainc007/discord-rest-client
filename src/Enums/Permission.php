<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Enums;

use InvalidArgumentException;

/**
 * Discord permission bit flags (API v10).
 *
 * Discord sends and accepts permission sets as decimal strings, because the
 * bitfield is wider than 53 bits; PHP's 64-bit int holds every flag here.
 * Bits Discord adds later that are not listed stay intact in the raw strings
 * the data objects keep, they are only left out of {@see self::fromBitfield()}.
 */
enum Permission: int
{
    case CreateInstantInvite = 1 << 0;
    case KickMembers = 1 << 1;
    case BanMembers = 1 << 2;
    case Administrator = 1 << 3;
    case ManageChannels = 1 << 4;
    case ManageGuild = 1 << 5;
    case AddReactions = 1 << 6;
    case ViewAuditLog = 1 << 7;
    case PrioritySpeaker = 1 << 8;
    case Stream = 1 << 9;
    case ViewChannel = 1 << 10;
    case SendMessages = 1 << 11;
    case SendTtsMessages = 1 << 12;
    case ManageMessages = 1 << 13;
    case EmbedLinks = 1 << 14;
    case AttachFiles = 1 << 15;
    case ReadMessageHistory = 1 << 16;
    case MentionEveryone = 1 << 17;
    case UseExternalEmojis = 1 << 18;
    case ViewGuildInsights = 1 << 19;
    case Connect = 1 << 20;
    case Speak = 1 << 21;
    case MuteMembers = 1 << 22;
    case DeafenMembers = 1 << 23;
    case MoveMembers = 1 << 24;
    case UseVoiceActivity = 1 << 25;
    case ChangeNickname = 1 << 26;
    case ManageNicknames = 1 << 27;
    case ManageRoles = 1 << 28;
    case ManageWebhooks = 1 << 29;
    case ManageGuildExpressions = 1 << 30;
    case UseApplicationCommands = 1 << 31;
    case RequestToSpeak = 1 << 32;
    case ManageEvents = 1 << 33;
    case ManageThreads = 1 << 34;
    case CreatePublicThreads = 1 << 35;
    case CreatePrivateThreads = 1 << 36;
    case UseExternalStickers = 1 << 37;
    case SendMessagesInThreads = 1 << 38;
    case UseEmbeddedActivities = 1 << 39;
    case ModerateMembers = 1 << 40;
    case ViewCreatorMonetizationAnalytics = 1 << 41;
    case UseSoundboard = 1 << 42;
    case CreateGuildExpressions = 1 << 43;
    case CreateEvents = 1 << 44;
    case UseExternalSounds = 1 << 45;
    case SendVoiceMessages = 1 << 46;
    case SetVoiceChannelStatus = 1 << 48;
    case SendPolls = 1 << 49;
    case UseExternalApps = 1 << 50;
    case PinMessages = 1 << 51;
    case BypassSlowmode = 1 << 52;

    /**
     * Combine flags into the decimal string Discord expects.
     */
    public static function combine(self ...$permissions): string
    {
        $bitfield = 0;

        foreach ($permissions as $permission) {
            $bitfield |= $permission->value;
        }

        return (string) $bitfield;
    }

    /**
     * The known flags set in a Discord bitfield string.
     *
     * @return list<self>
     */
    public static function fromBitfield(string|int $bitfield): array
    {
        $value = self::toInt($bitfield);
        $permissions = [];

        foreach (self::cases() as $permission) {
            if (($value & $permission->value) === $permission->value) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * Whether this flag is set in a Discord bitfield string.
     */
    public function isIn(string|int $bitfield): bool
    {
        return (self::toInt($bitfield) & $this->value) === $this->value;
    }

    private static function toInt(string|int $bitfield): int
    {
        if (is_int($bitfield)) {
            return $bitfield;
        }

        if ($bitfield === '' || ! ctype_digit($bitfield)) {
            throw new InvalidArgumentException("Permission bitfield must be a non-negative integer string, got [{$bitfield}].");
        }

        return (int) $bitfield;
    }
}
