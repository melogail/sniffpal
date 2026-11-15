<?php

namespace App\Enums;

use App\Concerns\HasOptions;
use App\Contracts\HasLabel;

/**
 * What a moderator did about an upheld report. A dismissed report has no
 * resolution: its status is `rejected` and nothing was done to the target.
 */
enum ReportResolution: string implements HasLabel
{
    use HasOptions;

    case Warned             = 'warned';
    case ContentRemoved     = 'content_removed';
    case ContentPurged      = 'content_purged';
    case AccountDeactivated = 'account_deactivated';

    public function label(): string
    {
        return match ($this) {
            self::Warned             => 'Warned',
            self::ContentRemoved     => 'Content Removed',
            self::ContentPurged      => 'Content Purged',
            self::AccountDeactivated => 'Account Deactivated',
        };
    }

    /**
     * Whether this resolution can be applied to a report against the given target.
     */
    public function appliesTo(Reportable $target): bool
    {
        return match ($this) {
            self::Warned, self::AccountDeactivated => true,
            self::ContentRemoved                   => $target !== Reportable::User,
            self::ContentPurged                    => $target === Reportable::Pet,
        };
    }
}
