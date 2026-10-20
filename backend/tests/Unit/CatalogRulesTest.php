<?php

namespace Tests\Unit;

use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Requests\Admin\TechnologyRequest;
use PHPUnit\Framework\TestCase;

/**
 * Pure rules of the A5 category / technology forms (no application, no database).
 */
class CatalogRulesTest extends TestCase
{
    public function test_a_category_language_is_filled_when_any_field_has_text(): void
    {
        $this->assertFalse(CategoryRequest::isFilled(null));
        $this->assertFalse(CategoryRequest::isFilled('web'));
        $this->assertFalse(CategoryRequest::isFilled(['name' => '', 'slug' => null]));
        $this->assertFalse(CategoryRequest::isFilled(['name' => '   ', 'slug' => "\n"]));
        $this->assertFalse(CategoryRequest::isFilled(['name' => ['nested'], 'slug' => 12]));
        $this->assertTrue(CategoryRequest::isFilled(['name' => 'Web']));
        $this->assertTrue(CategoryRequest::isFilled(['name' => null, 'slug' => 'web']));
    }

    public function test_icon_keys_are_short_identifiers(): void
    {
        foreach (['react', 'Laravel', 'simple-icons:laravel', 'brand/react.svg', 'ts_2'] as $icon) {
            $this->assertSame(1, preg_match(TechnologyRequest::ICON_PATTERN, $icon), $icon);
        }
        foreach (['', '-react', 'react icon', '<svg>', 'javascript:alert(1)()', "react\n"] as $icon) {
            $this->assertSame(0, preg_match(TechnologyRequest::ICON_PATTERN, $icon), $icon);
        }
    }
}
