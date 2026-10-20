<?php

namespace App\Models;

use App\Enums\Locale;
use Database\Factories\ProjectImageTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_image_id', 'locale', 'alt_text'])]
class ProjectImageTranslation extends Model
{
    /** @use HasFactory<ProjectImageTranslationFactory> */
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
     * @return BelongsTo<ProjectImage, $this>
     */
    public function projectImage(): BelongsTo
    {
        return $this->belongsTo(ProjectImage::class);
    }
}
