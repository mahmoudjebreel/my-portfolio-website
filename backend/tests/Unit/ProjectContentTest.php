<?php

namespace Tests\Unit;

use App\Support\ProjectContent;
use PHPUnit\Framework\TestCase;

class ProjectContentTest extends TestCase
{
    private const COMPLETE = [
        'title' => 'Portfolio',
        'slug' => 'portfolio-site',
        'short_description' => 'Short',
        'description' => 'Long',
        'problem' => null,
        'solution' => null,
        'cover_image_alt' => null,
    ];

    public function test_complete_content_needs_every_required_field_and_a_valid_slug(): void
    {
        $this->assertTrue(ProjectContent::isComplete(self::COMPLETE));

        foreach (ProjectContent::REQUIRED as $field) {
            $this->assertFalse(ProjectContent::isComplete([$field => null] + self::COMPLETE), $field);
            $this->assertFalse(ProjectContent::isComplete([$field => '   '] + self::COMPLETE), $field);
        }

        foreach (['Portfolio', 'portfolio_site', 'mawqi--shakhsi', '-a', 'موقع'] as $slug) {
            $this->assertFalse(ProjectContent::isComplete(['slug' => $slug] + self::COMPLETE), $slug);
        }
        $this->assertFalse(ProjectContent::isComplete(null));
    }

    public function test_filled_means_any_field_has_a_value(): void
    {
        $this->assertFalse(ProjectContent::isFilled(null));
        $this->assertFalse(ProjectContent::isFilled(['title' => '', 'slug' => null, 'problem' => '  ']));
        $this->assertTrue(ProjectContent::isFilled(['problem' => 'Something']));
        $this->assertTrue(ProjectContent::isFilled(['title' => 'Only a title']));
    }

    public function test_incomplete_locales_lists_english_and_arabic_in_order(): void
    {
        $this->assertSame([], ProjectContent::incompleteLocales(['en' => self::COMPLETE, 'ar' => ['slug' => 'mawqi-shakhsi'] + self::COMPLETE]));
        $this->assertSame(['ar'], ProjectContent::incompleteLocales(['en' => self::COMPLETE]));
        $this->assertSame(['en', 'ar'], ProjectContent::incompleteLocales([]));
        $this->assertSame(['en'], ProjectContent::incompleteLocales(['en' => ['title' => 'Only a title'], 'ar' => self::COMPLETE]));
    }
}
