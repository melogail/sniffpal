<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `status` is indexed for the moderation queue: Nova's `ReportStatusFilter`,
     * `Report::pending()` and the `PendingReports` metric all filter on it, and
     * `user_id` only reaches the queue through the composite index's leading
     * column, which those queries do not touch. `user_id` is nullable and nulls
     * on delete so a reporter leaving does not erase strikes against others.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('resolution')->nullable();
            $table->text('moderator_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('resolved_by');
            $table->index(['reportable_type', 'reportable_id', 'status'], 'reports_reportable_status_index');
            $table->index(['subject_user_id', 'status', 'resolved_at'], 'reports_subject_status_resolved_at_index');
            $table->index(['user_id', 'reportable_type', 'reportable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
