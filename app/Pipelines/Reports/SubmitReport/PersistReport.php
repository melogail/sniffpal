<?php

namespace App\Pipelines\Reports\SubmitReport;

use App\Models\Report;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Write the report row. `subject_user_id` is filled here so the moderation
 * record survives the target being deleted. Duplicate pending reports are
 * refused by EnsureNotAlreadyReported one step earlier; the table carries no
 * unique index, so a member can report the same target again after a decision.
 */
class PersistReport
{
    public function handle(SubmitReportContext $context, Closure $next): mixed
    {
        $reportable = $context->reportable();

        $report     = Report::create([
            'user_id'         => $context->reporter->getKey(),
            'subject_user_id' => $context->reportableAsTarget()->reportSubject()?->getKey(),
            'reportable_type' => Relation::getMorphAlias($reportable::class),
            'reportable_id'   => $reportable->getKey(),
            'reason'          => $context->reason,
            'description'     => $context->description,
        ]);

        $context->setReport($report);

        return $next($context);
    }
}
