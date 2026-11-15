<?php

namespace App\Pipelines\Reports\Resolve;

use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Models\Report;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Write the decision onto the report and onto every pending sibling.
 *
 * Runs before ApplyResolution on purpose: the cleanup flows delete pending
 * reports on removed content, so the siblings have to be decided (and therefore
 * kept) before the content goes. `Reviewed` is a holding state and touches only
 * the selected report.
 */
class RecordDecision
{
    public function handle(ResolveReportContext $context, Closure $next): mixed
    {
        $reports = $context->status === ReportStatus::Reviewed
            ? Report::query()->whereKey($context->report->getKey())->get()
            : $this->siblings($context)->get();

        $reports->each(function (Report $report) use ($context): void {
            $report->status         = $context->status;
            $report->resolution     = $context->resolution;
            $report->moderator_note = $context->note;
            $report->resolved_by    = $context->moderator->getKey();
            $report->resolved_at    = Carbon::now();
            $report->save();
        });

        // The selected report was re-fetched above; the later steps read the context's instance.
        $context->report->refresh();
        $context->setDecided($reports);

        return $next($context);
    }

    /**
     * @return Builder<Report>
     */
    protected function siblings(ResolveReportContext $context): Builder
    {
        $report = $context->report;

        return Report::query()->where(fn (Builder $query): Builder => $query
            ->whereKey($report->getKey())
            ->orWhere(fn (Builder $query): Builder => $query
                ->pending()
                ->where('reportable_type', $report->reportable_type)
                ->where('reportable_id', $report->reportable_id))
            ->when(
                $context->resolution === ReportResolution::AccountDeactivated && $report->subject_user_id !== null,
                fn (Builder $query): Builder => $query->orWhere(fn (Builder $query): Builder => $query
                    ->pending()
                    ->where('subject_user_id', $report->subject_user_id)),
            ));
    }
}
