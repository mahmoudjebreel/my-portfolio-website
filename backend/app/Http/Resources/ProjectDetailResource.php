<?php

namespace App\Http\Resources;

use App\Enums\Locale;
use App\Models\Project;
use App\Support\PublicFile;
use Illuminate\Http\Request;

/**
 * Public detail of a published project. Adds the long texts, the gallery,
 * language alternates and SEO data to the summary shape.
 *
 * Also expects `images.translations` constrained to the requested locale.
 *
 * @mixin Project
 */
class ProjectDetailResource extends ProjectResource
{
    /**
     * Slugs of the translations that actually exist, keyed by locale.
     *
     * @var array<string, string>
     */
    private array $alternateSlugs = [];

    /**
     * @param  array<string, string>  $slugsByLocale
     */
    public function withAlternates(array $slugsByLocale): static
    {
        $this->alternateSlugs = $slugsByLocale;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->localeTranslation();
        $summary = parent::toArray($request);

        return [
            'slug' => $summary['slug'],
            'locale' => $translation->locale->value,
            'title' => $summary['title'],
            'short_description' => $summary['short_description'],
            'description' => $translation->description,
            'problem' => $translation->problem,
            'solution' => $translation->solution,
            'is_featured' => $summary['is_featured'],
            'cover' => $summary['cover'],
            'images' => $this->images
                ->map(function ($image) use ($translation) {
                    $url = PublicFile::url($image->path);

                    return $url === null ? null : [
                        'url' => $url,
                        'alt' => $image->translations->first()?->alt_text ?: $translation->title,
                        'width' => $image->width,
                        'height' => $image->height,
                    ];
                })
                ->filter()
                ->values(),
            'categories' => $summary['categories'],
            'technologies' => $summary['technologies'],
            'links' => $summary['links'],
            'published_at' => $summary['published_at'],
            // Only languages that really have a translation get a slug; the rest are null.
            'alternates' => collect(Locale::cases())
                ->mapWithKeys(fn (Locale $locale) => [$locale->value => $this->alternateSlugs[$locale->value] ?? null])
                ->all(),
            'seo' => [
                'title' => $translation->title,
                'description' => $translation->short_description,
                'image_url' => $summary['cover']['url'] ?? null,
            ],
        ];
    }
}
