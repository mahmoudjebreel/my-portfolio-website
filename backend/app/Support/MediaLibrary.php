<?php

namespace App\Support;

use App\Enums\Locale;
use App\Models\Project;
use App\Models\ProjectImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The media library (project covers + gallery images), shared by the admin API and the
 * Filament panel. Reads the existing relations only; never deletes, detaches or replaces.
 *
 * An "item" is a plain array: kind (cover|gallery), id, project (model), path, width, height,
 * sort_order, alt per language, updated_at. describe() turns it into display data without
 * ever exposing the stored path or any disk location.
 */
final class MediaLibrary
{
    public const DISK = 'public';

    /** Image types the preview serves (never SVG or other active content). */
    public const PREVIEW_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

    /**
     * Covers and gallery images, newest first, optionally filtered. A portfolio holds few
     * images, so both sources are merged in memory; file details are read later, per page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function items(?int $projectId = null, ?string $kind = null, string $search = ''): Collection
    {
        $search = mb_strtolower(trim($search));
        $items = collect();

        if ($kind !== 'gallery') {
            Project::query()
                ->whereNotNull('cover_image_path')
                ->where('cover_image_path', '<>', '')
                ->when($projectId !== null, fn ($query) => $query->whereKey($projectId))
                ->with('translations')
                ->get()
                ->each(fn (Project $project) => $items->push(self::coverItem($project)));
        }

        if ($kind !== 'cover') {
            ProjectImage::query()
                ->when($projectId !== null, fn ($query) => $query->where('project_id', $projectId))
                ->with(['project.translations', 'translations'])
                ->get()
                ->each(fn (ProjectImage $image) => $items->push(self::galleryItem($image)));
        }

        return $items
            ->filter(fn (array $item) => $search === '' || self::matches($item, $search))
            ->sortByDesc(fn (array $item) => [$item['updated_at']?->getTimestamp() ?? 0, $item['kind'] === 'cover' ? 1 : 0, $item['id']])
            ->values();
    }

    /**
     * One item, or a 404 for an unknown id or a project without a cover.
     *
     * @return array<string, mixed>
     */
    public static function find(string $kind, int $id): array
    {
        if ($kind === 'cover') {
            $project = Project::query()->with('translations')->findOrFail($id);
            abort_if(! is_string($project->cover_image_path) || trim($project->cover_image_path) === '', 404);

            return self::coverItem($project);
        }

        abort_unless($kind === 'gallery', 404);

        return self::galleryItem(ProjectImage::query()->with(['project.translations', 'translations'])->findOrFail($id));
    }

    /**
     * Display data for one item. `available` is false when the stored path is unsafe or the
     * file is missing; the preview URL is built by the caller (API or panel route).
     *
     * @param  array<string, mixed>  $item
     * @param  callable(string, int): string  $previewUrl
     * @return array<string, mixed>
     */
    public static function describe(array $item, callable $previewUrl): array
    {
        $path = self::normalizedPath($item['path']);
        $available = PublicFile::url($path) !== null;
        $disk = Storage::disk(self::DISK);

        $width = $item['width'];
        $height = $item['height'];
        if ($available && ($width === null || $height === null)) {
            $size = @getimagesize($disk->path($path));
            [$width, $height] = is_array($size) ? [$size[0], $size[1]] : [null, null];
        }

        return [
            'key' => "{$item['kind']}-{$item['id']}",
            'kind' => $item['kind'],
            'id' => $item['id'],
            'file_name' => $path === '' ? null : basename($path),
            'mime_type' => $available ? $disk->mimeType($path) : null,
            'size' => $available ? $disk->size($path) : null,
            'width' => $width,
            'height' => $height,
            'sort_order' => $item['sort_order'],
            'alt' => $item['alt'],
            'available' => $available,
            'preview_url' => $available ? $previewUrl($item['kind'], $item['id']) : null,
            'project' => [
                'id' => $item['project']->id,
                'status' => $item['project']->status->value,
                'title' => self::titles($item['project']),
            ],
            'updated_at' => $item['updated_at']?->toIso8601ZuluString(),
        ];
    }

    /**
     * Streams the image: only safe relative paths of existing files on the public disk, only
     * raster image types, never as a download or as HTML.
     *
     * @param  array<string, mixed>  $item
     */
    public static function previewResponse(array $item): BinaryFileResponse
    {
        $path = self::normalizedPath($item['path']);
        abort_if(PublicFile::url($path) === null, 404);

        $disk = Storage::disk(self::DISK);
        $type = $disk->mimeType($path);
        abort_unless(in_array($type, self::PREVIEW_TYPES, true), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    /**
     * Project choices for filters and uploads: id => titles per language.
     *
     * @return Collection<int, array{id: int, title: array<string, string|null>}>
     */
    public static function projectOptions(): Collection
    {
        return Project::query()->with('translations')->orderByDesc('updated_at')->get()
            ->map(fn (Project $project) => ['id' => $project->id, 'title' => self::titles($project)])
            ->values();
    }

    /**
     * @return array<string, string|null>
     */
    public static function titles(Project $project): array
    {
        $titles = [];
        foreach (Locale::cases() as $locale) {
            $titles[$locale->value] = $project->translations->first(fn ($t) => $t->locale === $locale)?->title;
        }

        return $titles;
    }

    private static function normalizedPath(mixed $path): string
    {
        return is_string($path) ? str_replace('\\', '/', trim($path)) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function coverItem(Project $project): array
    {
        $alt = [];
        foreach (Locale::cases() as $locale) {
            $alt[$locale->value] = $project->translations->first(fn ($t) => $t->locale === $locale)?->cover_image_alt;
        }

        return [
            'kind' => 'cover', 'id' => $project->id, 'project' => $project, 'path' => $project->cover_image_path,
            'width' => null, 'height' => null, 'sort_order' => null, 'alt' => $alt, 'updated_at' => $project->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function galleryItem(ProjectImage $image): array
    {
        $alt = [];
        foreach (Locale::cases() as $locale) {
            $alt[$locale->value] = $image->translations->first(fn ($t) => $t->locale === $locale)?->alt_text;
        }

        return [
            'kind' => 'gallery', 'id' => $image->id, 'project' => $image->project, 'path' => $image->path,
            'width' => $image->width, 'height' => $image->height, 'sort_order' => $image->sort_order, 'alt' => $alt,
            'updated_at' => $image->updated_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function matches(array $item, string $search): bool
    {
        $haystack = [basename(self::normalizedPath($item['path'])), ...array_values(self::titles($item['project']))];
        foreach ($haystack as $value) {
            if (is_string($value) && str_contains(mb_strtolower($value), $search)) {
                return true;
            }
        }

        return false;
    }
}
