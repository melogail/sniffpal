<?php

namespace App\Nova\Filters;

use App\Enums\ReportResolution;

class ReportResolutionFilter extends EnumFilter
{
    public $name = 'Resolution';

    protected function column(): string
    {
        return 'resolution';
    }

    protected function enum(): string
    {
        return ReportResolution::class;
    }
}
