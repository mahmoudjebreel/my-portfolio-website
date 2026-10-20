<?php

namespace App\Models;

use App\Enums\TechnologyGroup;
use App\Models\Concerns\HasTranslations;
use Database\Factories\TechnologyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'group', 'icon', 'is_visible', 'sort_order'])]
class Technology extends Model
{
    /** @use HasFactory<TechnologyFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => TechnologyGroup::class,
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<TechnologyTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(TechnologyTranslation::class);
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_technology');
    }
}
