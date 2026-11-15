<?php

namespace App\Exceptions\Reports;

use App\Enums\Reportable;
use App\Enums\ReportResolution;
use InvalidArgumentException;

class ResolutionNotApplicable extends InvalidArgumentException
{
    public static function for(ReportResolution $resolution, Reportable $target): self
    {
        return new self(sprintf('"%s" cannot be applied to a report against a %s.', $resolution->label(), $target->value));
    }
}
