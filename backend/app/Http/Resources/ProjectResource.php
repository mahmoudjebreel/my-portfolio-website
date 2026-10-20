<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Support\PublicFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public summary of a published project (list items).
 *
 * Expects `translations`, `categories.translations` and `technologies.translations`
 * to be eager loaded and constrained to the requested locale.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->localeTranslation();

        return [
            'slug' => $translation->slug,
            'title' => $translation->title,
            'short_description' => $translation->short_description,
            'is_featured' => (bool) $this->is_featured,
            'cover' => $this->cover($translation),
            'categories' => $this->categories->map(fn ($category) => [
                'slug' => $category->translations->first()->slug,
                'name' => $category->translations->first()->name,
            ])->values(),
            'technologies' => $this->technologies->map(fn ($technology) => [
                'slug' => $technology->slug,
                'name' => $technology->translations->first()?->display_name ?: $technology->name,
            ])->values(),
            'links' => [
                'github' => self::httpUrl($this->github_url),
                'live' => self::httpUrl($this->live_url),
            ],
            'published_at' => $this->published_at?->toIso8601ZuluString(),
        ];
    }

    protected function localeTranslation(): ProjectTranslation
    {
        return $this->translations->first();
    }

    /**
     * @return array{url: string, alt: string}|null
     */
    protected function cover(ProjectTranslation $translation): ?array
    {
        $url = PublicFile::url($this->cover_image_path);

        return $url === null ? null : [
            'url' => $url,
            'alt' => $translation->cover_image_alt ?: $translation->title,
        ];
    }

    /**
     * Only well-formed http(s) links are exposed; anything else is null (never invented).
     */
    protected static function httpUrl(?string $value): ?string
    {
        if ($value === null || ($value = trim($value)) === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $value
            : null;
    }
}
