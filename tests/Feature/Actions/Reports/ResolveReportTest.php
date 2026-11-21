<?php

use App\Actions\Reports\ResolveReport;
use App\Enums\Reportable;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Exceptions\Reports\ResolutionNotApplicable;
use App\Models\Admin;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ModerationNoticeNotification;
use App\Notifications\ReportResolvedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

function reportedTarget(Reportable $type, User $author): Model
{
    return match ($type) {
        Reportable::Comment => Comment::factory()->for($author)->create(),
        Reportable::Review  => Review::factory()->for($author)->create(),
        Reportable::Pet     => Pet::factory()->for($author)->create(),
        Reportable::User    => $author,
    };
}

function decide(Report $report, ReportStatus $status, ?ReportResolution $resolution = null, ?string $note = null, bool $notify = true): Collection
{
    return app(ResolveReport::class)->handle($report, Admin::factory()->create(), $status, $resolution, $note, $notify);
}

test('records the decision, the moderator and the note on the report', function () {
    $report  = Report::factory()->forReportable(Review::factory()->create())->create();
    $decided = decide($report, ReportStatus::Resolved, ReportResolution::Warned, ' <b>Be</b> civil ');

    expect($decided)->toHaveCount(1);

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Resolved)
        ->and($report->resolution)->toBe(ReportResolution::Warned)
        ->and($report->moderator_note)->toBe('Be civil')
        ->and($report->resolved_by)->not->toBeNull()
        ->and($report->resolved_at)->not->toBeNull();
});

test('dismissing tells the reporter, leaves the content and says nothing to the subject', function (Reportable $type) {
    Notification::fake();
    $author = User::factory()->create();
    $target = reportedTarget($type, $author);
    $report = Report::factory()->forReportable($target)->create();

    decide($report, ReportStatus::Rejected);

    expect($report->fresh()->status)->toBe(ReportStatus::Rejected);
    $this->assertModelExists($target);
    Notification::assertSentTo($report->user, ReportResolvedNotification::class, fn ($notification) => $notification->toArray($report->user)['message_key'] === 'notifications.report_dismissed');
    Notification::assertNotSentTo($author, ModerationNoticeNotification::class);
})->with(Reportable::cases());

test('warning notifies the subject and the reporter and counts as a strike', function (Reportable $type) {
    Notification::fake();
    $author = User::factory()->create();
    $report = Report::factory()->forReportable(reportedTarget($type, $author))->create();

    decide($report, ReportStatus::Resolved, ReportResolution::Warned, 'First warning.');

    expect($author->strikeCount())->toBe(1);
    Notification::assertSentTo($author, ModerationNoticeNotification::class, fn ($notification, array $channels) => $channels === ['database']
        && $notification->toArray($author)['message_key'] === 'notifications.moderation_warning'
        && $notification->toArray($author)['note'] === 'First warning.');
    Notification::assertSentTo($report->user, ReportResolvedNotification::class, fn ($notification) => $notification->toArray($report->user)['message_key'] === 'notifications.report_upheld');
})->with(Reportable::cases());

test('removing content deletes the target through the application action and keeps the decided reports', function (Reportable $type) {
    Notification::fake();
    $author  = User::factory()->create();
    $target  = reportedTarget($type, $author);
    $report  = Report::factory()->forReportable($target)->create();
    $sibling = Report::factory()->forReportable($target)->create();
    $other   = Report::factory()->forReportable(reportedTarget($type, User::factory()->create()))->create();
    $decided = decide($report, ReportStatus::Resolved, ReportResolution::ContentRemoved);

    expect($decided->modelKeys())->toEqualCanonicalizing([$report->getKey(), $sibling->getKey()]);
    expect($target->fresh()->trashed())->toBeTrue();
    expect($sibling->fresh()->status)->toBe(ReportStatus::Resolved)
        ->and($sibling->fresh()->resolution)->toBe(ReportResolution::ContentRemoved)
        ->and($other->fresh()->status)->toBe(ReportStatus::Pending)
        ->and($author->strikeCount())->toBe(1);
    Notification::assertSentTo($author, ModerationNoticeNotification::class, fn ($notification) => $notification->toArray($author)['message_key'] === 'notifications.moderation_content_removed');
    Notification::assertSentTo($report->user, ReportResolvedNotification::class);
    Notification::assertSentTo($sibling->user, ReportResolvedNotification::class);
})->with([Reportable::Comment, Reportable::Review, Reportable::Pet]);

test('purging a listing hard deletes it', function () {
    $pet    = Pet::factory()->create();
    $report = Report::factory()->forReportable($pet)->create();

    decide($report, ReportStatus::Resolved, ReportResolution::ContentPurged);

    $this->assertDatabaseMissing('pets', ['id' => $pet->getKey()]);
    expect($report->fresh()->status)->toBe(ReportStatus::Resolved);
});

test('deactivating closes every pending report against the member and mails them', function () {
    Notification::fake();
    $author    = User::factory()->create();
    $profile   = Report::factory()->forReportable($author)->create();
    $listing   = Report::factory()->forReportable(Pet::factory()->for($author)->create())->create();
    $unrelated = Report::factory()->forReportable(Comment::factory()->create())->create();

    $decided   = decide($profile, ReportStatus::Resolved, ReportResolution::AccountDeactivated);

    expect($author->fresh()->isActive())->toBeFalse()
        ->and($decided->modelKeys())->toEqualCanonicalizing([$profile->getKey(), $listing->getKey()])
        ->and($listing->fresh()->resolution)->toBe(ReportResolution::AccountDeactivated)
        ->and($unrelated->fresh()->status)->toBe(ReportStatus::Pending);
    Notification::assertSentTo($author, ModerationNoticeNotification::class, fn ($notification, array $channels) => $channels === ['mail']);
    Notification::assertSentTo($profile->user, ReportResolvedNotification::class);
    Notification::assertSentTo($listing->user, ReportResolvedNotification::class);
});

test('marking under review saves the note and notifies nobody', function () {
    Notification::fake();
    $report = Report::factory()->forReportable(Review::factory()->create())->create();

    decide($report, ReportStatus::Reviewed, null, 'Waiting for a second opinion.');

    expect($report->fresh()->status)->toBe(ReportStatus::Reviewed)
        ->and($report->fresh()->moderator_note)->toBe('Waiting for a second opinion.');
    Notification::assertNothingSent();
});

test('does not notify the reporter when asked not to', function () {
    Notification::fake();
    $report = Report::factory()->forReportable(Review::factory()->create())->create();

    decide($report, ReportStatus::Rejected, notify: false);

    Notification::assertNothingSent();
});

test('refuses a resolution the target does not support and writes nothing', function () {
    $user   = User::factory()->create();
    $report = Report::factory()->forReportable($user)->create();

    expect(fn () => decide($report, ReportStatus::Resolved, ReportResolution::ContentRemoved))
        ->toThrow(ResolutionNotApplicable::class);

    expect($report->fresh()->status)->toBe(ReportStatus::Pending);
});

test('refuses a resolved status without a resolution and a dismissal with one', function () {
    $report = Report::factory()->forReportable(Review::factory()->create())->create();

    expect(fn () => decide($report, ReportStatus::Resolved))->toThrow(InvalidArgumentException::class);
    expect(fn () => decide($report, ReportStatus::Rejected, ReportResolution::Warned))->toThrow(InvalidArgumentException::class);
    expect(fn () => decide($report, ReportStatus::Pending))->toThrow(InvalidArgumentException::class);
});

test('still records the decision when the target has already disappeared', function () {
    Notification::fake();
    $review = Review::factory()->create();
    $report = Report::factory()->forReportable($review)->create();
    $review->forceDelete();

    decide($report, ReportStatus::Resolved, ReportResolution::ContentRemoved);

    expect($report->fresh()->status)->toBe(ReportStatus::Resolved);
    Notification::assertSentTo($report->user, ReportResolvedNotification::class);
});

test('one upheld target is one strike however many members reported it', function () {
    $author = User::factory()->create();
    $target = reportedTarget(Reportable::Pet, $author);
    $report = Report::factory()->forReportable($target)->create();
    Report::factory()->forReportable($target)->create();

    decide($report, ReportStatus::Resolved, ReportResolution::ContentRemoved);

    expect($author->strikeCount())->toBe(1)
        ->and($author->strikeCount(now()->subDays(90)))->toBe(1)
        ->and($author->reportsAgainst()->count())->toBe(2);
});
