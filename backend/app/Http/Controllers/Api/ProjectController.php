<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocaleRequest;
use App\Http\Requests\Api\ProjectIndexRequest;
use App\Http\Resources\ProjectDetailResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    /**
     * Published projects that have a translation in the requested locale.
     * Order: featured first, then sort_order, newest published, id.
     */
    public function index(ProjectIndexRequest $request): AnonymousResourceCollection
    {
        $locale = $request->contentLocale()->value;
        $category = $request->validated('category');
        $technology = $request->validated('technology');

        $projects = $this->visibleIn($locale)
            ->when($category !== null, fn (Builder $query) => $query->whereHas(
                'categories.translations',
                fn (Builder $translation) => $translation->where('locale', $locale)->where('slug', $category),
            ))
            ->when($technology !== null, fn (Builder $query) => $query->whereHas(
                'technologies',
                fn (Builder $tech) => $tech->where('slug', $technology)->where('is_visible', true),
            ))
            ->when($request->onlyFeatured(), fn (Builder $query) => $query->where('is_featured', true))
            ->with($this->relations($locale))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ProjectResource::collection($projects);
    }

    /**
     * One published project, looked up by its slug in the requested locale only.
     * Every "not visible" case returns the same generic 404.
     */
    public function show(LocaleRequest $request, string $slug): ProjectDetailResource
    {
        $locale = $request->contentLocale()->value;

        $project = $this->visibleIn($locale)
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', $locale)->where('slug', $slug))
            ->with($this->relations($locale) + [
                'images.translations' => fn ($query) => $query->where('locale', $locale),
            ])
            ->first();

        abort_if($project === null, 404);

        $alternates = ProjectTranslation::query()
            ->where('project_id', $project->getKey())
            ->pluck('slug', 'locale')
            ->all();

        return (new ProjectDetailResource($project))->withAlternates($alternates);
    }

    /**
     * Server-side visibility rule: published AND translated into the locale.
     */
    private function visibleIn(string $locale): Builder
    {
        return Project::query()
            ->where('status', ProjectStatus::Published)
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', $locale));
    }

    /**
     * Eager loads (no N+1), each constrained to the requested locale.
     *
     * @return array<string, \Closure>
     */
    private function relations(string $locale): array
    {
        $inLocale = fn ($query) => $query->where('locale', $locale);

        return [
            'translations' => $inLocale,
            // Only categories that are translated in this locale can be shown.
            'categories' => fn ($query) => $query
                ->whereHas('translations', $inLocale)
                ->orderBy('categories.sort_order')
                ->orderBy('categories.id'),
            'categories.translations' => $inLocale,
            'technologies' => fn ($query) => $query
                ->where('technologies.is_visible', true)
                ->orderBy('technologies.sort_order')
                ->orderBy('technologies.name'),
            'technologies.translations' => $inLocale,
        ];
    }
}
