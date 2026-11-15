<?php

namespace App\Pipelines\Reports\Resolve;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Notifications\ReportResolvedNotification;
use Closure;

/**
 * Close the loop with everyone whose report this decision answered. Reporters
 * are never told who else reported, and never hear about a "reviewed" hold.
 */
class NotifyReporters
{
    public function handle(ResolveReportContext $context, Closure $next): mixed
    {
        if (! $context->notifyReporter || $context->status === ReportStatus::Reviewed) {
            return $next($context);
        }

        $context->decided()
            ->loadMissing('user')
            ->unique('user_id')
            ->each(fn (Report $report) => $report->user?->notify(new ReportResolvedNotification($report)));

        return $next($context);
    }
}
