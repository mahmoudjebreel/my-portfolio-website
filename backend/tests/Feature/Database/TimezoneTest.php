<?php

namespace Tests\Feature\Database;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_session_time_zone_is_utc(): void
    {
        $this->assertSame('+00:00', DB::scalar('SELECT @@session.time_zone'));
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_timestamp_round_trips_unchanged(): void
    {
        $moment = Carbon::create(2026, 3, 29, 1, 30, 15, 'UTC');

        $project = Project::factory()->create(['published_at' => $moment]);

        // Raw column value is exactly what was written ...
        $this->assertSame('2026-03-29 01:30:15', DB::table('projects')->where('id', $project->id)->value('published_at'));

        // ... the server interprets it as that UTC instant ...
        $this->assertSame($moment->getTimestamp(), (int) DB::table('projects')->where('id', $project->id)->value(DB::raw('UNIX_TIMESTAMP(published_at)')));

        // ... and Eloquent reads back the same instant.
        $this->assertTrue($project->fresh()->published_at->equalTo($moment));
    }
}
