<?php

use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ModerationNoticeNotification;
use App\Notifications\ReportResolvedNotification;
use Illuminate\Support\Facades\Notification;

const RESOLVE_REPORT_ACTION = '/nova-api/reports/action?action=resolve-report';

test('dismisses a report and records who decided', function () {
    $admin  = Admin::factory()->create();
    $report = Report::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson(RESOLVE_REPORT_ACTION, ['resources' => [$report->getKey()], 'decision' => 'dismiss'])
        ->assertOk()
        ->assertJsonPath('message', '1 report(s) marked as Rejected.');

    expect($report->fresh()->status)->toBe(ReportStatus::Rejected)
        ->and($report->fresh()->resolved_by)->toBe($admin->getKey());
});

test('removes a reported comment and reports how many reports the decision closed', function () {
    $admin   = Admin::factory()->create();
    $comment = Comment::factory()->create();
    $report  = Report::factory()->forReportable($comment)->create();
    Report::factory()->forReportable($comment)->create();

    $this->actingAs($admin, 'admin')
        ->postJson(RESOLVE_REPORT_ACTION, [
            'resources' => [$report->getKey()],
            'decision'  => ReportResolution::ContentRemoved->value,
            'note'      => 'Harassment.',
        ])
        ->assertOk()
        ->assertJsonPath('message', '2 report(s) marked as Resolved (Content Removed).');

    $this->assertModelMissing($comment);
    expect(Report::query()->where('status', ReportStatus::Resolved)->count())->toBe(2);
});

test('rolls the whole selection back and says so when a resolution does not fit a target', function () {
    $admin   = Admin::factory()->create();
    $listing = Report::factory()->forReportable(Pet::factory()->create())->create();
    $profile = Report::factory()->forReportable(User::factory()->create())->create();

    $this->actingAs($admin, 'admin')
        ->postJson(RESOLVE_REPORT_ACTION, [
            'resources' => [$listing->getKey(), $profile->getKey()],
            'decision'  => ReportResolution::ContentPurged->value,
        ])
        ->assertOk()
        ->assertJsonPath('danger', fn (string $message): bool => str_starts_with($message, 'Nothing was changed.'));

    expect($listing->fresh()->status)->toBe(ReportStatus::Pending)
        ->and($profile->fresh()->status)->toBe(ReportStatus::Pending);
    $this->assertDatabaseHas('pets', ['id' => $listing->reportable_id]);
});

test('returns 422 to a decision that is not offered', function () {
    $admin  = Admin::factory()->create();
    $report = Report::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson(RESOLVE_REPORT_ACTION, ['resources' => [$report->getKey()], 'decision' => 'banish'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('decision');

    expect($report->fresh()->status)->toBe(ReportStatus::Pending);
});

test('lists reports against listings and profiles in the queue', function () {
    $admin = Admin::factory()->create();
    Report::factory()->forReportable(Pet::factory()->create())->create();
    Report::factory()->forReportable(User::factory()->create())->create();

    $this->actingAs($admin, 'admin')
        ->getJson('/nova-api/reports')
        ->assertOk()
        ->assertJsonCount(2, 'resources');
});

test('selecting sibling reports decides each target once and notifies the subject once', function () {
    Notification::fake();

    $admin  = Admin::factory()->create();
    $owner  = User::factory()->create();
    $pet    = Pet::factory()->for($owner)->create();
    $first  = Report::factory()->forReportable($pet)->create();
    $second = Report::factory()->forReportable($pet)->create();

    $this->actingAs($admin, 'admin')
        ->postJson(RESOLVE_REPORT_ACTION, [
            'resources' => [$first->getKey(), $second->getKey()],
            'decision'  => ReportResolution::ContentRemoved->value,
        ])
        ->assertOk()
        ->assertJsonPath('message', '2 report(s) marked as Resolved (Content Removed).');

    Notification::assertSentToTimes($owner, ModerationNoticeNotification::class, 1);
    Notification::assertSentToTimes($first->user, ReportResolvedNotification::class, 1);
    Notification::assertSentToTimes($second->user, ReportResolvedNotification::class, 1);
});

test('a warning needs a note', function () {
    $admin  = Admin::factory()->create();
    $report = Report::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson(RESOLVE_REPORT_ACTION, [
            'resources' => [$report->getKey()],
            'decision'  => ReportResolution::Warned->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('note');

    expect($report->fresh()->status)->toBe(ReportStatus::Pending);
});
