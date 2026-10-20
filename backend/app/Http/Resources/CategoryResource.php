<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a category. `translations` must be eager loaded and
 * constrained to the requested locale (only translated categories are listed).
 *
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->translations->first();

        return [
            'slug' => $translation->slug,
            'name' => $translation->name,
            'projects_count' => (int) $this->projects_count,
        ];
    }
}
