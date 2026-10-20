<?php

namespace App\Http\Resources;

use App\Models\Technology;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a technology. `translations` must be eager loaded and
 * constrained to the requested locale.
 *
 * @mixin Technology
 */
class TechnologyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->translations->first();

        return [
            'slug' => $this->slug,
            // Brand names are canonical; a translated display name is used only when one exists.
            'name' => $translation?->display_name ?: $this->name,
            'group' => $this->group->value,
            'icon' => $this->icon,
            'description' => $translation?->description,
        ];
    }
}
