<?php

namespace App\Actions\Catalog;

use App\Models\Technology;

/**
 * Deletes a technology (and its translations). Refused while the technology is linked to
 * any project (A5 rule): unlink it from those projects first.
 */
final class DeleteTechnology
{
    public function handle(Technology $technology): bool
    {
        if ($technology->projects()->exists()) {
            return false;
        }

        return (bool) $technology->delete();
    }
}
