<?php

namespace App\Contracts;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A model that can be reported. The report flow asks the target itself who is
 * accountable for it, rather than trusting a user id in the request.
 */
interface Reportable
{
    /**
     * @return MorphMany<Report, covariant Model>
     */
    public function reports(): MorphMany;

    /**
     * The one user answerable for this content; null when the author is gone.
     */
    public function reportSubject(): ?User;
}
