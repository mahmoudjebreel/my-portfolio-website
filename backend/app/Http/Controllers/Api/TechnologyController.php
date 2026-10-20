<?php

namespace App\Http\Controllers\Api;

use App\Enums\TechnologyGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocaleRequest;
use App\Http\Resources\TechnologyResource;
use App\Models\Technology;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TechnologyController extends Controller
{
    /**
     * Visible technologies, grouped in the fixed group order, then by sort order and name.
     */
    public function index(LocaleRequest $request): AnonymousResourceCollection
    {
        $locale = $request->contentLocale();
        $groupOrder = array_flip(array_map(fn (TechnologyGroup $group) => $group->value, TechnologyGroup::cases()));

        $technologies = Technology::query()
            ->where('is_visible', true)
            ->with(['translations' => fn ($query) => $query->where('locale', $locale->value)])
            ->get()
            ->sortBy([
                fn (Technology $a, Technology $b) => $groupOrder[$a->group->value] <=> $groupOrder[$b->group->value],
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        return TechnologyResource::collection($technologies);
    }
}
