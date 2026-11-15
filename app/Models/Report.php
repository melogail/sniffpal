<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A user report filed against a reportable model, triaged in the back office.
 *
 * `status` is deliberately not mass assignable: it is a moderator decision, the
 * column defaults to `pending`, and a Form Request forwarding validated() must
 * never be able to file a report that is already resolved. The moderation
 * Action sets it explicitly.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $subject_user_id
 * @property string $reportable_type
 * @property int $reportable_id
 * @property ReportReason $reason
 * @property string|null $description
 * @property ReportStatus $status
 * @property ReportResolution|null $resolution
 * @property string|null $moderator_note
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'subject_user_id',
    'reportable_type',
    'reportable_id',
    'reason',
    'description',
    'metadata',
])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * `status` is out of #[Fillable] and the DB default only lands in the row,
     * never on the instance, so `Report::create([...])->status` would be null
     * in memory while `@property ReportStatus $status` promises an enum — and
     * an API Resource or Inertia prop built from that model would ship the lie.
     * The raw backing value is stored here; the cast resolves it on access.
     *
     * @var array{status: string}
     */
    protected $attributes = [
        'status' => ReportStatus::Pending->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason'      => ReportReason::class,
            'status'      => ReportStatus::class,
            'resolution'  => ReportResolution::class,
            'resolved_at' => 'datetime',
            'metadata'    => 'array',
        ];
    }

    /**
     * The reported model. `withTrashed()` only applies to soft-deleting targets
     * (listings): a listing removed by a decision must still be reachable from
     * the report that removed it.
     *
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * The user who filed the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user accountable for the reported content (or the reported profile).
     * Filled at submit time so it survives the target being deleted.
     *
     * @return BelongsTo<User, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /**
     * The moderator who made the decision.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by');
    }

    /**
     * Resolved reports held against the given user: the strike count.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function upheldAgainst(Builder $query, User $subject, ?Carbon $since = null): Builder
    {
        return $query
            ->where('subject_user_id', $subject->getKey())
            ->where('status', ReportStatus::Resolved)
            ->when($since, fn (Builder $query, Carbon $since): Builder => $query->where('resolved_at', '>=', $since));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withStatus(Builder $query, ReportStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Reports still awaiting a moderator decision.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Pending);
    }
}
