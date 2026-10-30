<?php

namespace Tests\Feature\Admin;

use App\Jobs\ProcessUserImport;
use App\Models\User;
use App\Models\UserImport;
use App\Services\Imports\UserImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserImportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('user_imports');
        Queue::fake();
        Http::fake();
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($this->admin)->withSession(['campaign' => 'test', 'campaign_name' => 'Test']);
    }

    public function test_user_access_exposes_import_controls_and_upload_is_private_and_queued(): void
    {
        $this->get(route('admin.users.index'))->assertOk()
            ->assertSee('Import users from CSV')->assertSee('Download CSV template');
        $run = $this->upload();
        $this->assertSame('queued', $run->status);
        $this->assertSame('preview', $run->phase);
        Storage::disk('user_imports')->assertExists($run->sourcePath());
        Queue::assertPushed(ProcessUserImport::class, fn ($job) => $job->importId === $run->id && $job->connection === 'user-imports');
        $this->assertDatabaseCount('users', 1);
        $this->getJson(route('admin.user-imports.show', $run))->assertOk()->assertDontSee('StrongPass1!')->assertDontSee('source.csv');
    }

    public function test_preview_then_explicit_start_imports_only_eligible_accounts_and_deletes_source(): void
    {
        $run = $this->upload("username,full_name,password,email\none,One,StrongPass1!,one@example.test\ntwo,Two,weak,two@example.test\none,Again,StrongPass1!,other@example.test\n");
        $this->process($run);
        $run->refresh();
        $this->assertSame('ready', $run->status);
        $this->assertSame([3, 1, 1, 1], [$run->processed, $run->accepted, $run->skipped, $run->rejected]);
        $this->assertDatabaseCount('users', 1);
        $this->postJson(route('admin.user-imports.start', $run))->assertAccepted();
        $this->postJson(route('admin.user-imports.start', $run))->assertStatus(409);
        $this->process($run->refresh());
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertTrue(Hash::check('StrongPass1!', User::where('username', 'one')->sole()->password));
        $this->assertDatabaseMissing('users', ['username' => 'two']);
        Storage::disk('user_imports')->assertMissing($run->sourcePath());
        Http::assertNothingSent();
        $report = $this->get(route('admin.user-imports.report', $run))->assertOk()->streamedContent();
        $this->assertStringContainsString('password', $report);
        $this->assertStringNotContainsString('StrongPass1!', $report);
        $this->assertStringNotContainsString('one@example.test', $report);
    }

    public function test_all_import_routes_reject_other_roles_and_another_super_admin_cannot_access_a_run(): void
    {
        $run = $this->upload();
        foreach ([User::ROLE_AGENT, User::ROLE_TEAM_LEADER, User::ROLE_ADMIN] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson(route('admin.user-imports.show', $run))->assertForbidden();
            $this->postJson(route('admin.user-imports.start', $run))->assertForbidden();
            $this->deleteJson(route('admin.user-imports.cancel', $run))->assertForbidden();
            $this->get(route('admin.user-imports.report', $run))->assertForbidden();
            $this->get(route('admin.user-imports.template'))->assertForbidden();
            $this->postJson(route('admin.user-imports.store'), ['csv' => $this->file()])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
        $this->getJson(route('admin.user-imports.show', $run))->assertNotFound();
        $this->postJson(route('admin.user-imports.start', $run))->assertNotFound();
        $this->get(route('admin.user-imports.report', $run))->assertNotFound();
        $this->deleteJson(route('admin.user-imports.cancel', $run))->assertNotFound();
    }

    public function test_upload_validation_and_privileged_role_opt_in(): void
    {
        $this->postJson(route('admin.user-imports.store'), [])->assertUnprocessable()->assertJsonValidationErrors('csv');
        $this->postJson(route('admin.user-imports.store'), ['csv' => UploadedFile::fake()->createWithContent('bad.csv', 'wrong,headers')])
            ->assertUnprocessable()->assertJsonValidationErrors('csv');
        $run = $this->upload("username,full_name,password,role\nleader,Leader,StrongPass1!,Team Leader\n");
        $this->process($run);
        $this->assertSame(1, $run->fresh()->rejected);
        $this->postJson(route('admin.user-imports.start', $run))->assertStatus(409);
        $allowed = $this->upload("username,full_name,password,role\nleader,Leader,StrongPass1!,Team Leader\n", true);
        $this->process($allowed);
        $this->postJson(route('admin.user-imports.start', $allowed))->assertAccepted();
        $this->process($allowed->refresh());
        $this->assertDatabaseHas('users', ['username' => 'leader', 'role' => User::ROLE_TEAM_LEADER]);
    }

    public function test_checkpoints_keep_duplicates_consistent_across_batches_and_completed_jobs_are_noops(): void
    {
        $content = "username,full_name,password,email\n";
        for ($i = 0; $i < 105; $i++) {
            $content .= "bulk{$i},Bulk {$i},StrongPass1!,bulk{$i}@example.test\n";
        }
        $content .= "bulk0,Again,StrongPass1!,other@example.test\nother,Other,StrongPass1!,bulk1@example.test\n";
        $run = $this->upload($content);
        $service = app(UserImportService::class);
        $this->assertTrue($service->process($run->id, 'preview'));
        $this->assertSame(50, $run->fresh()->processed);
        $this->assertGreaterThan(0, $run->fresh()->cursor_offset);
        $this->process($run);
        $this->assertSame([107, 105, 1, 1], array_values($run->fresh()->only(['processed', 'accepted', 'skipped', 'rejected'])));
        $this->postJson(route('admin.user-imports.start', $run))->assertAccepted();
        $this->assertFalse($service->process($run->id, 'preview'));
        $this->process($run->refresh());
        $this->assertDatabaseCount('users', 106);
        $this->assertFalse($service->process($run->id, 'import'));
        $this->assertDatabaseCount('users', 106);
        $this->assertDatabaseCount('user_import_identities', 0);
    }

    public function test_cancel_expiry_and_role_revocation_remove_source_and_stop_work(): void
    {
        $cancelled = $this->upload();
        $this->deleteJson(route('admin.user-imports.cancel', $cancelled))->assertOk();
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertFalse(app(UserImportService::class)->process($cancelled->id, 'preview'));
        Storage::disk('user_imports')->assertMissing($cancelled->sourcePath());
        $expired = $this->upload();
        $expired->update(['expires_at' => now()->subMinute()]);
        $this->artisan('users:imports-prune')->assertSuccessful();
        $this->assertSame('expired', $expired->fresh()->status);
        Storage::disk('user_imports')->assertMissing($expired->sourcePath());
        $revoked = $this->upload();
        $this->admin->update(['role' => User::ROLE_ADMIN]);
        $this->process($revoked);
        $this->assertSame('failed', $revoked->fresh()->status);
        Storage::disk('user_imports')->assertMissing($revoked->sourcePath());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_failure_rolls_back_batch_and_never_serializes_exception_secrets(): void
    {
        $run = $this->upload("username,full_name,password\none,One,StrongPass1!\ntwo,Two,StrongPass2!\n");
        $this->process($run);
        $this->postJson(route('admin.user-imports.start', $run))->assertAccepted();
        User::creating(function (User $user): void {
            if ($user->username === 'two') {
                throw new \RuntimeException('SensitiveSqlBinding123!');
            }
        });
        $this->process($run->refresh());
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->accepted);
        $this->assertDatabaseCount('users', 1);
        $this->getJson(route('admin.user-imports.show', $run))->assertOk()->assertDontSee('SensitiveSqlBinding123!')->assertDontSee('StrongPass1!');
        Storage::disk('user_imports')->assertMissing($run->sourcePath());
    }

    public function test_changed_source_is_rejected_after_preview_and_invalid_upload_types_are_denied(): void
    {
        $this->postJson(route('admin.user-imports.store'), ['csv' => UploadedFile::fake()->createWithContent('staff.txt', "username,full_name,password\none,One,StrongPass1!\n")])
            ->assertUnprocessable()->assertJsonValidationErrors('csv');
        $this->postJson(route('admin.user-imports.store'), ['csv' => UploadedFile::fake()->create('large.csv', 20481)])
            ->assertUnprocessable()->assertJsonValidationErrors('csv');
        $run = $this->upload();
        $this->process($run);
        Storage::disk('user_imports')->put($run->sourcePath(), "username,full_name,password\ninjected,Injected,StrongPass1!\n");
        $this->postJson(route('admin.user-imports.start', $run))->assertAccepted();
        $this->process($run->refresh());
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertDatabaseCount('users', 1);
        Storage::disk('user_imports')->assertMissing($run->sourcePath());
    }

    public function test_unauthenticated_import_requests_require_sign_in(): void
    {
        $run = $this->upload();
        auth()->logout();
        $this->getJson(route('admin.user-imports.show', $run))->assertUnauthorized();
        $this->postJson(route('admin.user-imports.start', $run))->assertUnauthorized();
        $this->deleteJson(route('admin.user-imports.cancel', $run))->assertUnauthorized();
        $this->get(route('admin.user-imports.report', $run))->assertRedirect(route('login'));
    }

    public function test_transient_database_deadlock_retries_the_same_checkpoint_without_failing_the_import(): void
    {
        $run = $this->upload();
        $this->process($run);
        $this->postJson(route('admin.user-imports.start', $run))->assertAccepted();
        $attempts = 0;
        User::creating(function () use (&$attempts): void {
            if (++$attempts === 1) {
                throw new \Illuminate\Database\DeadlockException('database is locked');
            }
        });

        $this->assertTrue(app(UserImportService::class)->process($run->id, 'import'));
        $this->assertSame('queued', $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->cursor_offset);
        $this->assertDatabaseCount('users', 1);
        Storage::disk('user_imports')->assertExists($run->sourcePath());
        $this->assertFalse(app(UserImportService::class)->process($run->id, 'import'));
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertDatabaseCount('users', 2);
    }

    private function file(?string $content = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('staff.csv', $content ?? "username,full_name,password\none,One,StrongPass1!\n");
    }

    private function upload(?string $content = null, bool $privileged = false): UserImport
    {
        $response = $this->postJson(route('admin.user-imports.store'), ['csv' => $this->file($content), 'allow_privileged_roles' => $privileged]);
        $response->assertAccepted()->assertJsonPath('phase', 'preview')->assertJsonPath('status', 'queued')->assertDontSee('StrongPass1!');

        return UserImport::findOrFail($response->json('id'));
    }

    private function process(UserImport $run): void
    {
        for ($i = 0; $i < 20; $i++) {
            if (! app(UserImportService::class)->process($run->id, $run->phase)) {
                return;
            }
        }
        $this->fail('Synthetic import did not finish within its expected batches.');
    }
}
