<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocaleRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Categories translated into the requested locale, in display order.
     * `projects_count` counts only published projects that have a translation in that locale.
     */
    public function index(LocaleRequest $request): AnonymousResourceCollection
    {
        $locale = $request->contentLocale()->value;

        $categories = Category::query()
            ->whereHas('translations', fn ($query) => $query->where('locale', $locale))
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->withCount(['projects' => fn ($query) => $query
                ->where('status', ProjectStatus::Published)
                ->whereHas('translations', fn ($translation) => $translation->where('locale', $locale)),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return CategoryResource::collection($categories);
    }
}
