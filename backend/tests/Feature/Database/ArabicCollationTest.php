<?php

namespace Tests\Feature\Database;

use App\Enums\Locale;
use App\Models\CategoryTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Risk R5 probe: how does utf8mb4_unicode_ci treat Arabic names that differ only by hamza?
 *
 * OBSERVED on MySQL 8.4.3 (2026-10-15): utf8mb4_unicode_ci treats 'أدوات' (alef with hamza
 * above, U+0623) and 'ادوات' (bare alef, U+0627) as DIFFERENT values: the comparison returns 0
 * and the unique index (locale, name) accepts both rows. Uniqueness is therefore NOT
 * hamza-insensitive; if near-duplicate Arabic names must be prevented, that has to happen in
 * application validation. This test pins the observed behaviour so a collation change is noticed.
 */
class ArabicCollationTest extends TestCase
{
    use RefreshDatabase;

    private const WITH_HAMZA = 'أدوات';    // U+0623 ARABIC LETTER ALEF WITH HAMZA ABOVE

    private const WITHOUT_HAMZA = 'ادوات'; // U+0627 ARABIC LETTER ALEF

    public function test_collation_distinguishes_hamza_variants(): void
    {
        $this->assertNotSame(self::WITH_HAMZA, self::WITHOUT_HAMZA);

        $this->assertSame(0, (int) DB::scalar(
            'SELECT ? = ? COLLATE utf8mb4_unicode_ci',
            [self::WITH_HAMZA, self::WITHOUT_HAMZA],
        ));
    }

    public function test_unique_locale_name_index_accepts_names_differing_only_by_hamza(): void
    {
        CategoryTranslation::factory()->locale(Locale::Ar)->create(['name' => self::WITH_HAMZA, 'slug' => 'adawat-1']);
        CategoryTranslation::factory()->locale(Locale::Ar)->create(['name' => self::WITHOUT_HAMZA, 'slug' => 'adawat-2']);

        $this->assertSame(2, CategoryTranslation::query()->where('locale', 'ar')->count());
        $this->assertSame(1, CategoryTranslation::query()->where('name', self::WITH_HAMZA)->count());
        $this->assertSame(1, CategoryTranslation::query()->where('name', self::WITHOUT_HAMZA)->count());
    }
}
