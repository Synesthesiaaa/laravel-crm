<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserImportQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        Storage::fake('user_imports');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))
            ->withSession(['campaign' => 'test', 'campaign_name' => 'Test']);
    }

    public function test_upload_inserts_a_real_database_job_even_with_a_sync_default(): void
    {
        $response = $this->postJson(route('admin.user-imports.store'), ['csv' => $this->csv()]);
        $response->assertAccepted()->assertJsonPath('status', 'queued');
        $job = DB::table('jobs')->where('queue', 'user-imports')->sole();
        $this->assertStringContainsString($response->json('id'), $job->payload);
        $this->assertStringNotContainsString('SensitiveFixture1!', $job->payload);
        $this->assertDatabaseCount('user_imports', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_missing_jobs_table_rolls_back_metadata_and_removes_the_source(): void
    {
        Schema::drop('jobs');
        $this->postJson(route('admin.user-imports.store'), ['csv' => $this->csv()])
            ->assertUnprocessable()->assertJsonValidationErrors('csv')->assertDontSee('SensitiveFixture1!');
        $this->assertDatabaseCount('user_imports', 0);
        $this->assertSame([], Storage::disk('user_imports')->allFiles());
        $this->assertDatabaseCount('users', 1);
    }

    private function csv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('staff.csv', "username,full_name,password\nfixture,Fixture,SensitiveFixture1!\n");
    }
}
