<?php

namespace App\Exceptions\Reports;

use Illuminate\Validation\ValidationException;

/**
 * This user has already reported this target and that report is still pending.
 *
 * Thrown by SubmitReport\EnsureNotAlreadyReported, one `exists()` before the
 * insert. The table carries no unique index: a member may report the same
 * target again once an earlier report was decided, and the rare double submit
 * yields two pending rows that one decision closes together.
 */
class AlreadyReported extends ValidationException
{
    public static function make(): self
    {
        return self::withMessages([
            'report' => __('You have already reported this.'),
        ]);
    }
}
