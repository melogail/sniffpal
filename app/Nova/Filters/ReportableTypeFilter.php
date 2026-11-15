<?php

namespace App\Nova\Filters;

use App\Enums\Reportable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Narrow the queue to one kind of target: listing, profile, comment or review.
 */
class ReportableTypeFilter extends Filter
{
    public $name      = 'Target';

    public $component = 'select-filter';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('reportable_type', $value);
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return collect(Reportable::cases())
            ->mapWithKeys(fn (Reportable $case): array => [Str::headline($case->value) => $case->value])
            ->all();
    }
}
