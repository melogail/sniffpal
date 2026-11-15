<?php

namespace App\Notifications;

use App\Enums\ReportResolution;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the member a report was about what was decided: a warning, removed
 * content, or a deactivated account. The reporter's identity is never included.
 *
 * A deactivated member cannot read the in-app inbox any more, so that case goes
 * by mail; the others are inbox rows that link to the community guidelines.
 */
class ModerationNoticeNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public Report $report) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->report->resolution === ReportResolution::AccountDeactivated ? ['mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : null;
        $reason = $this->report->reason->label();

        $mail   = (new MailMessage)
            ->subject(__('mail.moderation.deactivated.subject', [], $locale))
            ->line(__('mail.moderation.deactivated.line', ['reason' => $reason], $locale));

        if ($this->report->moderator_note !== null) {
            $mail->line($this->report->moderator_note);
        }

        return $mail
            ->line(__('mail.moderation.appeal', [], $locale))
            ->action(__('mail.moderation.action', [], $locale), route('guidelines'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id'       => $this->report->id,
            'reportable_type' => $this->report->reportable_type,
            'reason'          => $this->report->reason->value,
            'resolution'      => $this->report->resolution?->value,
            'note'            => $this->report->moderator_note,
            'message_key'     => $this->report->resolution === ReportResolution::Warned
                ? 'notifications.moderation_warning'
                : 'notifications.moderation_content_removed',
            'message_replace' => [],
            'url'             => route('guidelines', absolute: false),
            'type'            => 'moderation',
        ];
    }
}
