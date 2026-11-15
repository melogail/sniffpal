<?php

namespace App\Nova\Metrics;

use App\Models\Report;
use DateTimeInterface;
use Illuminate\Support\Str;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

class ReportsByType extends Partition
{
    public function calculate(NovaRequest $request): PartitionResult
    {
        return $this->count($request, Report::class, groupBy: 'reportable_type')
            ->label(fn (?string $value): string => $value === null ? 'Unknown' : Str::headline($value));
    }

    public function cacheFor(): ?DateTimeInterface
    {
        return null;
    }

    public function uriKey(): string
    {
        return 'reports-by-type';
    }
}
