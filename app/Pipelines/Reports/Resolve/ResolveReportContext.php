<?php

namespace App\Pipelines\Reports\Resolve;

use App\Enums\Reportable;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * One moderator decision about one report. `decided` is filled by
 * RecordDecision with every report the decision closed: the one selected plus
 * the pending siblings on the same target (and, for a deactivation, every
 * pending report against the same member).
 */
class ResolveReportContext
{
    /** @var Collection<int, Report> */
    protected Collection $decided;

    protected ?Model $reportable = null;

    public function __construct(
        public readonly Report $report,
        public readonly Admin $moderator,
        public readonly ReportStatus $status,
        public readonly ?ReportResolution $resolution,
        public readonly ?string $note,
        public readonly bool $notifyReporter,
    ) {
        $this->decided = new Collection;
    }

    public function targetType(): Reportable
    {
        return Reportable::from($this->report->reportable_type);
    }

    /**
     * The reported model, read once before any step can delete it.
     */
    public function reportable(): ?Model
    {
        return $this->reportable ??= $this->report->reportable()->first();
    }

    /**
     * @param  Collection<int, Report>  $reports
     */
    public function setDecided(Collection $reports): void
    {
        $this->decided = $reports;
    }

    /**
     * @return Collection<int, Report>
     */
    public function decided(): Collection
    {
        return $this->decided;
    }
}
