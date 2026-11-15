<?php

use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Report;

test('puts a decided report back in the queue and clears the decision', function () {
    $admin  = Admin::factory()->create();
    $report = Report::factory()->resolved()->create(['moderator_note' => 'Oops.']);

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reports/action?action=reopen-report', ['resources' => [$report->getKey()]])
        ->assertOk()
        ->assertJsonPath('message', '1 report(s) reopened.');

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Pending)
        ->and($report->resolution)->toBeNull()
        ->and($report->moderator_note)->toBeNull()
        ->and($report->resolved_by)->toBeNull()
        ->and($report->resolved_at)->toBeNull();
});
