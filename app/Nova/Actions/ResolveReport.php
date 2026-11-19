<?php

namespace App\Nova\Actions;

use App\Actions\Reports\ResolveReport as ResolveReportAction;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

/**
 * The moderator's one control: pick a decision, add a note, apply.
 *
 * The decision Select folds status and resolution into one list so a moderator
 * never has to combine two fields: `review` and `dismiss` are statuses with no
 * resolution; every other option is `Resolved` plus that resolution. The work is
 * App\Actions\Reports\ResolveReport, resolved with `app()` inside handle()
 * because a Nova action has no constructor injection (.ai/rules/nova-actions.md).
 *
 * Whole selection in one transaction, failure reported as a sentence
 * (.ai/rules/nova-actions.md). A resolution the target does not support throws
 * before anything is written, so the sentence is true for that case too.
 */
class ResolveReport extends Action
{
    public const DECISION_REVIEW  = 'review';

    public const DECISION_DISMISS = 'dismiss';

    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name                  = 'Resolve Report';

    /**
     * @param  Collection<int, Report>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            $decision              = (string) $fields->get('decision');
            $note                  = $fields->get('note');
            $notify                = (bool) $fields->get('notify_reporter', true);

            [$status, $resolution] = match ($decision) {
                self::DECISION_REVIEW  => [ReportStatus::Reviewed, null],
                self::DECISION_DISMISS => [ReportStatus::Rejected, null],
                default                => [ReportStatus::Resolved, ReportResolution::from($decision)],
            };

            /** @var Admin $moderator */
            $moderator             = request()->user('admin');

            // A decision closes the sibling reports too, so a sibling further down the
            // selection is already decided and must not be re-applied or re-notified.
            $closed                = DB::transaction(function () use ($models, $moderator, $status, $resolution, $note, $notify): int {
                $decided = collect();

                foreach ($models as $report) {
                    if ($decided->contains($report->getKey())) {
                        continue;
                    }

                    $decided = $decided->merge(app(ResolveReportAction::class)
                        ->handle($report->fresh(), $moderator, $status, $resolution, is_string($note) ? $note : null, $notify)
                        ->modelKeys());
                }

                return $decided->unique()->count();
            });

            return ActionResponse::message(sprintf(
                '%d report(s) marked as %s%s.',
                $closed,
                $status->label(),
                $resolution === null ? '' : ' ('.$resolution->label().')',
            ));
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(sprintf(
                'Nothing was changed. The decision could not be applied to one of the selected reports, so the whole selection was rolled back: %s The failure has been logged.',
                $exception->getMessage(),
            ));
        }
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $options = [
            self::DECISION_REVIEW  => 'Mark under review (no action, nobody notified)',
            self::DECISION_DISMISS => 'Dismiss — no violation',
        ];

        foreach (ReportResolution::cases() as $resolution) {
            $options[$resolution->value] = match ($resolution) {
                ReportResolution::Warned             => 'Warn the member (strike)',
                ReportResolution::ContentRemoved     => 'Remove the content (strike)',
                ReportResolution::ContentPurged      => 'Purge the listing and its photos (strike)',
                ReportResolution::AccountDeactivated => 'Deactivate the member\'s account',
            };
        }

        return [
            Select::make('Decision', 'decision')
                ->options($options)
                ->rules('required', Rule::in(array_keys($options)))
                ->help('First upheld report: warn. Second: remove. Third in 90 days, or any scam / welfare / prohibited-animal case: deactivate. "Remove" applies to listings, comments and reviews; "Purge" to listings only.'),

            Textarea::make('Moderator note', 'note')
                ->rules('required_if:decision,'.ReportResolution::Warned->value, 'nullable', 'string', 'max:1000')
                ->help('Shown to the member when warning or removing content. Required for a warning.'),

            Boolean::make('Notify the reporter', 'notify_reporter')
                ->default(true),
        ];
    }
}
