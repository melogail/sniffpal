<?php

namespace App\Notifications;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

/**
 * Tells a reporter what came of their report. Says whether it was upheld or
 * dismissed, never what exactly was done or who else reported.
 */
class ReportResolvedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public Report $report) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $upheld = $this->report->status === ReportStatus::Resolved;

        return [
            'report_id'       => $this->report->id,
            'reportable_type' => $this->report->reportable_type,
            'reason'          => $this->report->reason->value,
            'status'          => $this->report->status->value,
            'message_key'     => $upheld ? 'notifications.report_upheld' : 'notifications.report_dismissed',
            'message_replace' => [],
            'url'             => null,
            'type'            => 'report_resolved',
        ];
    }
}
