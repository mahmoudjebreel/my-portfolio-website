<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public const APPLICATION_TABLES = [
        'categories',
        'category_translations',
        'technologies',
        'technology_translations',
        'projects',
        'project_translations',
        'project_category',
        'project_technology',
        'project_images',
        'project_image_translations',
        'contact_messages',
        'site_settings',
        'site_setting_translations',
        'users',
    ];

    public function test_all_application_tables_exist(): void
    {
        $this->assertCount(14, self::APPLICATION_TABLES);

        foreach (self::APPLICATION_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}]");
        }
    }

    public function test_users_table_has_is_admin_defaulting_to_false(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'is_admin'));

        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'is_admin');

        $this->assertSame('tinyint(1)', $column['type']);
        $this->assertFalse($column['nullable']);
        $this->assertSame('0', (string) $column['default']);
    }

    public function test_key_columns_exist(): void
    {
        $expected = [
            'categories' => ['id', 'sort_order', 'created_at', 'updated_at'],
            'category_translations' => ['id', 'category_id', 'locale', 'name', 'slug'],
            'technologies' => ['id', 'name', 'slug', 'group', 'icon', 'is_visible', 'sort_order'],
            'technology_translations' => ['id', 'technology_id', 'locale', 'display_name', 'description'],
            'projects' => ['id', 'cover_image_path', 'github_url', 'live_url', 'status', 'is_featured', 'sort_order', 'published_at'],
            'project_translations' => ['id', 'project_id', 'locale', 'title', 'slug', 'short_description', 'description', 'problem', 'solution', 'cover_image_alt'],
            'project_category' => ['project_id', 'category_id'],
            'project_technology' => ['project_id', 'technology_id'],
            'project_images' => ['id', 'project_id', 'path', 'width', 'height', 'sort_order'],
            'project_image_translations' => ['id', 'project_image_id', 'locale', 'alt_text'],
            'contact_messages' => ['id', 'name', 'email', 'subject', 'message', 'locale', 'status', 'read_at', 'notified_at', 'created_at'],
            'site_settings' => ['id', 'key', 'value'],
            'site_setting_translations' => ['id', 'site_setting_id', 'locale', 'value'],
        ];

        foreach ($expected as $table => $columns) {
            $this->assertTrue(Schema::hasColumns($table, $columns), "Table [{$table}] is missing one of: ".implode(', ', $columns));
        }

        // Pivot tables have no timestamps and no surrogate id.
        $this->assertSame(['project_id', 'category_id'], Schema::getColumnListing('project_category'));
        $this->assertSame(['project_id', 'technology_id'], Schema::getColumnListing('project_technology'));
    }

    public function test_application_tables_use_innodb_and_utf8mb4_unicode_ci(): void
    {
        $tables = collect(Schema::getTables(DB::connection()->getDatabaseName()))
            ->whereIn('name', self::APPLICATION_TABLES);

        $this->assertCount(14, $tables);

        foreach ($tables as $table) {
            $this->assertSame('utf8mb4_unicode_ci', $table['collation'], "Table [{$table['name']}] collation");
            $this->assertSame('InnoDB', $table['engine'], "Table [{$table['name']}] engine");
            $this->assertSame(DB::connection()->getDatabaseName(), $table['schema']);
        }
    }
}
