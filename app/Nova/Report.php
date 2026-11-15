<?php

namespace App\Nova;

use App\Enums\Reportable;
use App\Enums\ReportReason;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Models\Report as ReportModel;
use App\Models\User as UserModel;
use App\Nova\Actions\PurgeOrphanedReports;
use App\Nova\Actions\ReopenReport;
use App\Nova\Actions\ResolveReport;
use App\Nova\Filters\ReportableTypeFilter;
use App\Nova\Filters\ReportReasonFilter;
use App\Nova\Filters\ReportResolutionFilter;
use App\Nova\Filters\ReportStatusFilter;
use App\Nova\Metrics\PendingReports;
use App\Nova\Metrics\ReportsByStatus;
use App\Nova\Policies\ReportPolicy;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Resources\MergeValue;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Laravel\Nova\ResourceTool;

/**
 * The moderation queue. There was no Report resource in the legacy back office
 * at all, so the whole reporting feature had nowhere to be acted on.
 *
 * ## `status` is the moderator's decision and nothing else writes it
 *
 * `reports.status` is deliberately outside App\Models\Report's #[Fillable] —
 * the column defaults to `pending` and a Form Request forwarding validated()
 * must never be able to file a report that is already resolved. Every field on
 * this resource is therefore read only, including status: the only way to move
 * a report is Actions\ResolveReport (or ReopenReport), which goes through
 * App\Actions\Reports\ResolveReport and assigns the property explicitly. There is no create form and no update form (see
 * App\Nova\Policies\ReportPolicy); a report is user-filed evidence, not
 * something an admin authors or edits.
 *
 * ## Reaching the target
 *
 * `reportable_type` holds a morph alias (the backing values of
 * App\Enums\Reportable), so the MorphTo below lists exactly the Nova
 * resources those aliases map to. It is the link a moderator follows from
 * "somebody reported this" to the thing itself, where each target resource
 * carries its own actions.
 *
 * A report whose target has already been deleted resolves to a null
 * `reportable`. For a **pending** report that is a stranded row the cleanup
 * Actions exist to prevent (.ai/rules/actions.md); for a **resolved** one it is
 * expected — the decision removed the content and the row is kept as the
 * moderation record (strikes, who decided, the note). Either is cleared with
 * Actions\PurgeOrphanedReports, which refuses to touch a report whose target
 * still resolves. Nova's own delete button is off for this
 * resource (ReportPolicy::delete) so that a bulk "select all" cannot empty the
 * queue.
 *
 * @extends \App\Nova\Resource<ReportModel>
 */
class Report extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<ReportModel>
     */
    public static $model  = ReportModel::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<ReportPolicy>
     */
    public static $policy = ReportPolicy::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title  = 'id';

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group  = 'Moderation';

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'id', 'description',
    ];

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array<int, string>
     */
    public static $with   = ['user', 'subject', 'reportable', 'resolvedBy'];

    /**
     * Order the queue by what still needs deciding, then oldest first.
     *
     * Nova's own default is newest id first, which is the wrong end of a
     * moderation queue: the report that has been waiting longest is the one
     * that should be looked at next. Sorting on `created_at` alone was the
     * wrong end of a different axis, though, and only looked right on an empty
     * queue. It orders the **whole table**, so once there is any history page 1
     * is the oldest reports ever filed — overwhelmingly resolved and rejected
     * ones — and everything actually pending sinks towards the last page. A
     * moderator opening the queue has to page to the end to find work.
     *
     * Pending first fixes that without hiding anything, and it is a sort rather
     * than a default filter on purpose: a filtered default would fight the
     * status filter the resource already offers, and Nova has no way to say
     * "this filter is in force" in a way an admin can see.
     *
     * The primary key is the last tiebreaker because `created_at` is not
     * unique. Reports filed in the same second — which is what a spree looks
     * like, and exactly what a moderator is paging through — have no defined
     * order without it, so a row can appear on two pages or on none.
     *
     * The CASE names `reports.status` literally rather than through
     * `qualifyColumn()`: orderByRaw() only takes a literal-string, which is the
     * guard against a runtime value reaching the SQL. If the table is ever
     * renamed the query fails loudly on an unknown column, not silently.
     */
    public static function defaultOrderings(Builder $query): Builder
    {
        $model        = $query->getModel();

        $highPriority = ReportReason::highPriorityValues();

        return $query
            ->orderByRaw(
                'case when reports.status = ? then 0 else 1 end',
                [ReportStatus::Pending->value],
            )
            ->orderByRaw(
                'case when reports.reason in ('.implode(',', array_fill(0, count($highPriority), '?')).') then 0 else 1 end',
                $highPriority,
            )
            ->orderBy($model->getQualifiedCreatedAtColumn())
            ->orderBy($model->getQualifiedKeyName());
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field|Panel|ResourceTool|MergeValue>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Badge::make('Status', 'status')
                ->map([
                    ReportStatus::Pending->value  => 'warning',
                    ReportStatus::Reviewed->value => 'info',
                    ReportStatus::Resolved->value => 'success',
                    ReportStatus::Rejected->value => 'danger',
                ])
                ->labels($this->labelsFor(ReportStatus::cases()))
                ->sortable(),

            MorphTo::make('Reported', 'reportable')
                ->types([
                    Comment::class,
                    Review::class,
                    Pet::class,
                    User::class,
                ])
                ->exceptOnForms(),

            BelongsTo::make('Subject', 'subject', User::class)
                ->exceptOnForms()
                ->nullable()
                ->help('The member accountable for the reported content. Kept even after the content is deleted.'),

            Number::make('Strikes against subject', fn (): ?int => $this->resource->subject instanceof UserModel
                ? $this->resource->subject->strikeCount()
                : null)
                ->onlyOnDetail()
                ->help('Resolved reports held against this member, all time.'),

            Number::make('Other pending reports on this target', fn (): int => ReportModel::query()
                ->pending()
                ->where('reportable_type', $this->resource->reportable_type)
                ->where('reportable_id', $this->resource->reportable_id)
                ->whereKeyNot($this->resource->getKey())
                ->count())
                ->onlyOnDetail(),

            BelongsTo::make('Reported By', 'user', User::class)
                ->exceptOnForms()
                ->searchable(),

            Select::make('Reason', 'reason')
                ->options(ReportReason::class)
                ->displayUsingLabels()
                ->exceptOnForms()
                ->sortable(),

            Textarea::make('Description')
                ->exceptOnForms()
                ->alwaysShow(),

            Badge::make('Resolution', 'resolution')
                ->map([
                    ReportResolution::Warned->value             => 'info',
                    ReportResolution::ContentRemoved->value     => 'danger',
                    ReportResolution::ContentPurged->value      => 'danger',
                    ReportResolution::AccountDeactivated->value => 'danger',
                ])
                ->labels(collect(ReportResolution::cases())
                    ->mapWithKeys(fn (ReportResolution $case): array => [$case->value => $case->label()])
                    ->all())
                ->nullable()
                ->hideFromIndex(),

            Textarea::make('Moderator Note', 'moderator_note')
                ->exceptOnForms()
                ->alwaysShow(),

            BelongsTo::make('Resolved By', 'resolvedBy', Admin::class)
                ->exceptOnForms()
                ->nullable()
                ->hideFromIndex(),

            DateTime::make('Resolved At', 'resolved_at')
                ->exceptOnForms()
                ->hideFromIndex(),

            KeyValue::make('Metadata', 'metadata')
                ->keyLabel('Field')
                ->valueLabel('Value')
                ->exceptOnForms()
                ->onlyOnDetail(),

            DateTime::make('Filed At', 'created_at')
                ->exceptOnForms()
                ->sortable()
                ->filterable(),

        ];
    }

    /**
     * Badge labels keyed by backing value, taken from the enum's own label().
     *
     * @param  array<int, ReportStatus>  $cases
     * @return array<string, string>
     */
    protected function labelsFor(array $cases): array
    {
        return collect($cases)
            ->mapWithKeys(fn (ReportStatus $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Get the cards available for the request.
     *
     * @return array<int, Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [
            PendingReports::make()->refreshWhenActionsRun(),
            ReportsByStatus::make()->refreshWhenActionsRun(),
        ];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            ReportStatusFilter::make(),
            ReportableTypeFilter::make(),
            ReportReasonFilter::make(),
            ReportResolutionFilter::make(),
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            ResolveReport::make()
                ->confirmText('Records the decision on this report and every pending report on the same target, acts on the content or account where the decision says so, and tells the reporter the outcome.')
                ->confirmButtonText('Apply decision')
                ->cancelButtonText('Cancel'),

            ReopenReport::make(),

            PurgeOrphanedReports::make()
                ->confirmText('This permanently deletes the selected reports. It refuses to run if any of them still points at a comment or review that exists — those are evidence and belong in Change Status.')
                ->confirmButtonText('Purge orphans')
                ->cancelButtonText('Cancel'),
        ];
    }
}
