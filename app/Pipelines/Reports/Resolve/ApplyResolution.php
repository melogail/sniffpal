<?php

namespace App\Pipelines\Reports\Resolve;

use App\Actions\Comments\DeleteComment;
use App\Actions\Pets\DeletePet;
use App\Actions\Pets\PurgePet;
use App\Actions\Reviews\DeleteReview;
use App\Enums\ReportResolution;
use App\Exceptions\Reports\ResolutionNotApplicable;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Review;
use App\Models\User;
use Closure;

/**
 * Do what the resolution says, through the application's own delete Actions so
 * the polymorphic cleanup they carry runs. A target that has already vanished
 * is not an error: the decision is still recorded and the reporter still told.
 */
class ApplyResolution
{
    public function __construct(
        private readonly DeletePet $deletePet,
        private readonly PurgePet $purgePet,
        private readonly DeleteComment $deleteComment,
        private readonly DeleteReview $deleteReview,
    ) {}

    public function handle(ResolveReportContext $context, Closure $next): mixed
    {
        $resolution = $context->resolution;

        if ($resolution === null || $resolution === ReportResolution::Warned) {
            return $next($context);
        }

        if (! $resolution->appliesTo($context->targetType())) {
            throw ResolutionNotApplicable::for($resolution, $context->targetType());
        }

        if ($resolution === ReportResolution::AccountDeactivated) {
            $subject = $context->report->subject;

            if ($subject instanceof User && $subject->isActive()) {
                $subject->is_active = false;
                $subject->save();
            }

            return $next($context);
        }

        $reportable = $context->reportable();

        match (true) {
            $reportable instanceof Pet && $resolution === ReportResolution::ContentPurged => $this->purgePet->handle($reportable),
            $reportable instanceof Pet                                                    => $this->deletePet->handle($reportable),
            $reportable instanceof Comment                                                => $this->deleteComment->handle($reportable),
            $reportable instanceof Review                                                 => $this->deleteReview->handle($reportable),
            default                                                                       => null,
        };

        return $next($context);
    }
}
