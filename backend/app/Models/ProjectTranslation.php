<?php

namespace App\Models;

use App\Enums\Locale;
use Database\Factories\ProjectTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'locale', 'title', 'slug', 'short_description', 'description', 'problem', 'solution', 'cover_image_alt'])]
class ProjectTranslation extends Model
{
    /** @use HasFactory<ProjectTranslationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
