<?php

namespace App\Enums;

use App\Concerns\HasOptions;
use App\Contracts\HasLabel;

/**
 * Why a report was filed. One enum for every target; `forTarget()` is the
 * subset a reporter is offered and the Form Request validates against.
 */
enum ReportReason: string implements HasLabel
{
    use HasOptions;

    case Spam                 = 'spam';
    case Harassment           = 'harassment';
    case HateSpeech           = 'hate_speech';
    case FalseInformation     = 'false_information';
    case InappropriateContent = 'inappropriate_content';
    case ScamOrFraud          = 'scam_or_fraud';
    case AnimalWelfare        = 'animal_welfare';
    case ProhibitedAnimal     = 'prohibited_animal';
    case MisleadingListing    = 'misleading_listing';
    case DuplicateListing     = 'duplicate_listing';
    case NoLongerAvailable    = 'no_longer_available';
    case Impersonation        = 'impersonation';
    case FakeReview           = 'fake_review';
    case Other                = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam                 => 'Spam',
            self::Harassment           => 'Harassment',
            self::HateSpeech           => 'Hate Speech',
            self::FalseInformation     => 'False Information',
            self::InappropriateContent => 'Inappropriate Content',
            self::ScamOrFraud          => 'Scam or Fraud',
            self::AnimalWelfare        => 'Animal Welfare Concern',
            self::ProhibitedAnimal     => 'Prohibited Animal',
            self::MisleadingListing    => 'Misleading Listing',
            self::DuplicateListing     => 'Duplicate Listing',
            self::NoLongerAvailable    => 'No Longer Available',
            self::Impersonation        => 'Impersonation',
            self::FakeReview           => 'Fake Review',
            self::Other                => 'Other',
        };
    }

    /**
     * The reasons a reporter may pick for the given target.
     *
     * @return array<int, self>
     */
    public static function forTarget(Reportable $target): array
    {
        return match ($target) {
            Reportable::Pet     => [
                self::ScamOrFraud, self::AnimalWelfare, self::ProhibitedAnimal, self::MisleadingListing,
                self::DuplicateListing, self::NoLongerAvailable, self::Spam, self::InappropriateContent, self::Other,
            ],
            Reportable::User    => [
                self::ScamOrFraud, self::Impersonation, self::Harassment, self::Spam, self::InappropriateContent, self::Other,
            ],
            Reportable::Comment => [
                self::Spam, self::Harassment, self::HateSpeech, self::FalseInformation, self::InappropriateContent, self::Other,
            ],
            Reportable::Review  => [
                self::FakeReview, self::Harassment, self::HateSpeech, self::InappropriateContent, self::Spam, self::Other,
            ],
        };
    }

    /**
     * `options()` keyed by reportable type, for the client's report dialog.
     *
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    public static function optionsByTarget(): array
    {
        $options = [];

        foreach (Reportable::cases() as $target) {
            $options[$target->value] = array_map(
                fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
                self::forTarget($target),
            );
        }

        return $options;
    }

    /**
     * Reasons the moderation queue surfaces ahead of the rest.
     */
    public function isHighPriority(): bool
    {
        return match ($this) {
            self::ScamOrFraud, self::AnimalWelfare, self::ProhibitedAnimal,
            self::Impersonation, self::Harassment, self::HateSpeech => true,
            default                                                 => false,
        };
    }

    /**
     * Backing values of the high-priority reasons, for a raw ORDER BY CASE.
     *
     * @return array<int, string>
     */
    public static function highPriorityValues(): array
    {
        return array_map(
            fn (self $case): string => $case->value,
            array_values(array_filter(self::cases(), fn (self $case): bool => $case->isHighPriority())),
        );
    }
}
