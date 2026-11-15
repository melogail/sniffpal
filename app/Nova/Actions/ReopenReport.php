<?php

namespace App\Nova\Actions;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

/**
 * Put a decided report back in the queue (a mistaken dismissal, mostly).
 *
 * Undoes the record only, never the action: removed content stays removed and
 * a deactivated member stays deactivated until ReactivateUser says otherwise.
 */
class ReopenReport extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Reopen Report';

    /**
     * @param  Collection<int, Report>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            DB::transaction(function () use ($models): void {
                $models->each(function (Report $report): void {
                    $report->status         = ReportStatus::Pending;
                    $report->resolution     = null;
                    $report->moderator_note = null;
                    $report->resolved_by    = null;
                    $report->resolved_at    = null;
                    $report->save();
                });
            });
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger('Nothing was changed. One of the selected reports could not be reopened, so the whole selection was rolled back. The failure has been logged.');
        }

        return ActionResponse::message(sprintf('%d report(s) reopened.', $models->count()));
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
