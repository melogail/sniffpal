<?php

namespace App\Actions\Reports;

use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Report;
use App\Pipelines\Reports\Resolve\ApplyResolution;
use App\Pipelines\Reports\Resolve\NotifyReporters;
use App\Pipelines\Reports\Resolve\NotifySubject;
use App\Pipelines\Reports\Resolve\RecordDecision;
use App\Pipelines\Reports\Resolve\ResolveReportContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A moderator's decision on a report: record it, act on it, tell both sides.
 *
 * - `Rejected` is a dismissal: no resolution, nothing done, reporter told.
 * - `Resolved` needs a resolution and is the only status that acts on the target.
 * - `Reviewed` is a hold: the note is saved, nobody is notified.
 *
 * The only writer of `reports.status` besides Nova's ReopenReport; the column
 * is outside #[Fillable] on purpose (see App\Models\Report).
 *
 * @return Collection<int, Report> every report the decision closed
 */
class ResolveReport
{
    public function __construct(private readonly Pipeline $pipeline) {}

    /**
     * @return Collection<int, Report>
     */
    public function handle(
        Report $report,
        Admin $moderator,
        ReportStatus $status,
        ?ReportResolution $resolution = null,
        ?string $note = null,
        bool $notifyReporter = true,
    ): Collection {
        if ($status === ReportStatus::Pending) {
            throw new InvalidArgumentException('Pending is the state a report arrives in, not a decision.');
        }

        if (($status === ReportStatus::Resolved) !== ($resolution !== null)) {
            throw new InvalidArgumentException('A resolved report needs a resolution, and only a resolved report has one.');
        }

        $context = new ResolveReportContext(
            report: $report,
            moderator: $moderator,
            status: $status,
            resolution: $resolution,
            note: $note === null ? null : (trim(strip_tags($note)) ?: null),
            notifyReporter: $notifyReporter,
        );

        return DB::transaction(fn (): Collection => $this->pipeline
            ->send($context)
            ->through([
                RecordDecision::class,
                ApplyResolution::class,
                NotifySubject::class,
                NotifyReporters::class,
            ])
            ->then(fn (ResolveReportContext $completed): Collection => $completed->decided()));
    }
}
