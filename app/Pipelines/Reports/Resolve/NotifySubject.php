<?php

namespace App\Pipelines\Reports\Resolve;

use App\Notifications\ModerationNoticeNotification;
use Closure;

/**
 * Tell the member the decision was about, when something was done to them or
 * their content. A dismissal and a "reviewed" hold say nothing to the subject.
 */
class NotifySubject
{
    public function handle(ResolveReportContext $context, Closure $next): mixed
    {
        $subject = $context->report->subject;

        if ($context->resolution !== null && $subject !== null) {
            $subject->notify(new ModerationNoticeNotification($context->report));
        }

        return $next($context);
    }
}
