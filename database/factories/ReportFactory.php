<?php

namespace Database\Factories;

use App\Contracts\Reportable;
use App\Enums\ReportReason;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * A fresh, pending report against a new review. `status` is pending rather
     * than random: the cleanup flows delete only pending reports on removed
     * content, so a random status would make those tests flaky. Use
     * `resolved()` / `dismissed()` for a decided report.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'subject_user_id' => null,
            'reportable_type' => Relation::getMorphAlias(Review::class),
            'reportable_id'   => Review::factory(),
            'reason'          => fake()->randomElement(ReportReason::cases()),
            'description'     => fake()->optional(0.7)->paragraph(),
            'status'          => ReportStatus::Pending,
            'metadata'        => fake()->optional(0.5)->passthrough(
                fn (array $attributes): array => [
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                ],
            ),
        ];
    }

    /**
     * Point the report at an existing target and record who is accountable for it.
     */
    public function forReportable(Model&Reportable $reportable): static
    {
        return $this->state(fn (array $attributes): array => [
            'reportable_type' => $reportable->getMorphClass(),
            'reportable_id'   => $reportable->getKey(),
            'subject_user_id' => $reportable->reportSubject()?->getKey(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::Pending,
        ]);
    }

    public function resolved(ReportResolution $resolution = ReportResolution::ContentRemoved): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'      => ReportStatus::Resolved,
            'resolution'  => $resolution,
            'resolved_by' => Admin::factory(),
            'resolved_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'      => ReportStatus::Rejected,
            'resolved_by' => Admin::factory(),
            'resolved_at' => now(),
        ]);
    }
}
