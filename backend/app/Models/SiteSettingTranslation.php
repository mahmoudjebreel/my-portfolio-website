<?php

namespace App\Models;

use App\Enums\Locale;
use Database\Factories\SiteSettingTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['site_setting_id', 'locale', 'value'])]
class SiteSettingTranslation extends Model
{
    /** @use HasFactory<SiteSettingTranslationFactory> */
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
     * @return BelongsTo<SiteSetting, $this>
     */
    public function siteSetting(): BelongsTo
    {
        return $this->belongsTo(SiteSetting::class);
    }
}
