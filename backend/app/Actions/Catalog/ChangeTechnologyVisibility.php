<?php

namespace App\Actions\Catalog;

use App\Models\Technology;

/**
 * Shows or hides a technology on the public website. This is an explicit action of its own:
 * editing a technology's details still never changes its visibility.
 */
final class ChangeTechnologyVisibility
{
    public function handle(Technology $technology, bool $visible): Technology
    {
        $technology->is_visible = $visible;
        $technology->save();

        return $technology;
    }
}
