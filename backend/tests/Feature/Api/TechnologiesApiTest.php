<?php

namespace Tests\Feature\Api;

use App\Enums\TechnologyGroup;
use App\Models\Technology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnologiesApiTest extends TestCase
{
    use RefreshDatabase;

    private function tech(string $name, TechnologyGroup $group, int $order, bool $visible = true): Technology
    {
        return Technology::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'group' => $group,
            'icon' => null,
            'is_visible' => $visible,
            'sort_order' => $order,
        ]);
    }

    public function test_only_visible_technologies_are_listed_in_group_then_sort_order(): void
    {
        $this->tech('MySQL', TechnologyGroup::Database, 1);
        $this->tech('Laravel', TechnologyGroup::Backend, 2);
        $this->tech('PHP', TechnologyGroup::Backend, 1);
        $this->tech('React', TechnologyGroup::Frontend, 2);
        $this->tech('HTML5', TechnologyGroup::Frontend, 1);
        $this->tech('Hidden Tool', TechnologyGroup::Tools, 1, visible: false);

        $this->getJson('/api/technologies')
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => ['slug', 'name', 'group', 'icon', 'description']]])
            ->assertJsonPath('data.*.name', ['HTML5', 'React', 'PHP', 'Laravel', 'MySQL'])
            ->assertDontSee('Hidden Tool');
    }

    public function test_translated_display_name_is_used_only_for_its_locale(): void
    {
        $tech = $this->tech('MySQL', TechnologyGroup::Database, 1);
        $tech->translations()->create(['locale' => 'ar', 'display_name' => 'ماي إس كيو إل', 'description' => 'وصف عربي']);
        $tech->translations()->create(['locale' => 'en', 'display_name' => null, 'description' => 'English description']);

        $this->getJson('/api/technologies?locale=en')
            ->assertJsonPath('data.0.name', 'MySQL')
            ->assertJsonPath('data.0.description', 'English description')
            ->assertDontSee('وصف عربي');

        $this->getJson('/api/technologies?locale=ar')
            ->assertJsonPath('data.0.name', 'ماي إس كيو إل')
            ->assertJsonPath('data.0.description', 'وصف عربي')
            ->assertDontSee('English description');
    }

    public function test_missing_translation_falls_back_to_the_canonical_brand_name_only(): void
    {
        $this->tech('Laravel', TechnologyGroup::Backend, 1);

        $this->getJson('/api/technologies?locale=ar')
            ->assertJsonPath('data.0.name', 'Laravel')
            ->assertJsonPath('data.0.description', null)
            ->assertJsonPath('data.0.group', 'backend');
    }

    public function test_internal_fields_are_not_exposed(): void
    {
        $this->tech('PHP', TechnologyGroup::Backend, 7);

        $item = $this->getJson('/api/technologies')->json('data.0');

        foreach (['id', 'is_visible', 'sort_order', 'created_at', 'updated_at'] as $field) {
            $this->assertArrayNotHasKey($field, $item);
        }
    }

    public function test_empty_database_returns_an_empty_list(): void
    {
        $this->getJson('/api/technologies')->assertOk()->assertExactJson(['data' => []]);
    }
}
