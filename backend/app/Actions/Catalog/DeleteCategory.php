<?php

namespace App\Actions\Catalog;

use App\Models\Category;

/**
 * Deletes a category (and its translations). Refused while the category is linked to any
 * project (A5 rule): unlink it from those projects first.
 */
final class DeleteCategory
{
    public function handle(Category $category): bool
    {
        if ($category->projects()->exists()) {
            return false;
        }

        return (bool) $category->delete();
    }
}
