<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_import_preserves_hashing_encryption_activity_and_profile_without_external_requests(): void
    {
        Http::fake();
        DB::table('vicidial_servers')->insert([
            'campaign_code' => 'C01', 'server_name' => 'Synthetic',
            'api_url' => 'https://vicidial.example.test/agc/api.php',
            'db_host' => '127.0.0.1', 'db_username' => 'synthetic', 'db_password' => 'synthetic',
            'api_user' => 'synthetic', 'api_pass' => 'synthetic', 'is_active' => true,
        ]);
        $file = $this->csv([
            ['username', 'full_name', 'password', 'email', 'vici_user', 'vici_pass', 'extension', 'sip_password', 'default_campaign', 'auto_vici_login', 'default_blended', 'default_ingroups'],
            ['agent001', 'Agent, One', 'StrongPass1!', 'one@example.test', '0001', 'ViciSecret1!', '0010', 'SipSecret1!', 'C01', 'true', 'false', 'SALE,SUPPORT'],
        ], bom: true);

        $this->artisan('users:import', ['file' => $file])->assertSuccessful();

        $user = User::where('username', 'agent001')->sole();
        $this->assertSame('Agent, One', $user->full_name);
        $this->assertSame(User::ROLE_AGENT, $user->role);
        $this->assertTrue(Hash::check('StrongPass1!', $user->password));
        $this->assertSame('0001', $user->vici_user);
        $this->assertSame('0010', $user->extension);
        $this->assertSame('ViciSecret1!', $user->vici_pass);
        $this->assertSame('SipSecret1!', $user->sip_password);
        $this->assertNotSame('ViciSecret1!', $user->getRawOriginal('vici_pass'));
        $this->assertNotSame('SipSecret1!', $user->getRawOriginal('sip_password'));
        $this->assertSame('C01', $user->default_campaign);
        $this->assertTrue($user->auto_vici_login);
        $this->assertFalse($user->default_blended);
        $this->assertSame('SALE,SUPPORT', $user->default_ingroups);
        $this->assertDatabaseHas('activity_log', ['subject_type' => User::class, 'subject_id' => $user->id, 'event' => 'created']);
        Http::assertNothingSent();
    }

    public function test_dry_run_detects_duplicates_across_batches_without_persisting_accounts_or_activity(): void
    {
        $file = $this->csv([
            ['username', 'full_name', 'password', 'email'],
            ['one', 'One', 'StrongPass1!', 'one@example.test'],
            ['two', 'Two', 'StrongPass1!', 'two@example.test'],
            ['one', 'Again', 'StrongPass1!', 'other@example.test'],
            ['three', 'Three', 'StrongPass1!', 'two@example.test'],
        ]);

        $this->artisan('users:import', ['file' => $file, '--dry-run' => true, '--chunk' => 1])
            ->expectsOutputToContain('Processed: 4 | Eligible: 2 | Skipped: 1 | Rejected: 1')
            ->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_replays_skip_existing_and_soft_deleted_usernames_without_overwriting(): void
    {
        $existing = User::factory()->create(['username' => 'existing']);
        $deleted = User::factory()->create(['username' => 'deleted']);
        $deleted->delete();
        $file = $this->csv([
            ['username', 'full_name', 'password'],
            ['existing', 'Replacement', 'StrongPass1!'],
            ['deleted', 'Replacement', 'StrongPass1!'],
            ['new', 'New', 'StrongPass1!'],
        ]);

        $this->artisan('users:import', ['file' => $file, '--chunk' => 1])
            ->expectsOutputToContain('Processed: 3 | Created: 1 | Skipped: 2 | Rejected: 0')->assertSuccessful();
        $this->artisan('users:import', ['file' => $file])
            ->expectsOutputToContain('Created: 0 | Skipped: 3')->assertSuccessful();
        $this->assertSame($existing->full_name, $existing->fresh()->full_name);
        $this->assertSoftDeleted('users', ['id' => $deleted->id]);
    }

    public function test_rejects_invalid_rows_and_email_conflicts_but_imports_valid_rows_with_safe_report(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);
        $report = $this->unusedPath();
        $file = $this->csv([
            ['username', 'full_name', 'password', 'email', 'role'],
            ['weak', 'Weak', 'secretvalue', 'weak@example.test', 'Agent'],
            ['boss', 'Boss', 'StrongPass1!', 'boss@example.test', 'Super Admin'],
            ['conflict', 'Conflict', 'StrongPass1!', 'taken@example.test', 'Agent'],
            ['good', 'Good', '  StrongPass1!  ', 'good@example.test', 'Agent'],
            ['invalid', '', 'StrongPass1!', 'not-email', 'Unknown Role'],
        ]);

        $this->artisan('users:import', ['file' => $file, '--errors' => $report])
            ->expectsOutputToContain('Processed: 5 | Created: 1 | Skipped: 0 | Rejected: 4')->assertExitCode(1);
        $this->assertTrue(Hash::check('  StrongPass1!  ', User::where('username', 'good')->sole()->password));
        $contents = file_get_contents($report);
        $this->assertStringContainsString('record,errors', $contents);
        $this->assertStringNotContainsString('secretvalue', $contents);
        $this->assertStringNotContainsString('StrongPass1!', $contents);
        $this->assertStringNotContainsString('taken@example.test', $contents);
        $this->assertStringNotContainsString('Unknown Role', $contents);
    }

    public function test_privileged_roles_require_explicit_opt_in(): void
    {
        $file = $this->csv([
            ['username', 'full_name', 'password', 'role'],
            ['leader', 'Leader', 'StrongPass1!', 'Team Leader'],
            ['admin', 'Admin', 'StrongPass1!', 'Admin'],
            ['super', 'Super', 'StrongPass1!', 'Super Admin'],
        ]);
        $this->artisan('users:import', ['file' => $file, '--allow-privileged-roles' => true])->assertSuccessful();
        $this->assertSame(User::ROLES[1], User::where('username', 'leader')->sole()->role);
        $this->assertSame(User::ROLE_SUPER_ADMIN, User::where('username', 'super')->sole()->role);
    }

    public function test_invalid_headers_missing_files_options_and_existing_reports_fail_before_creating_accounts(): void
    {
        foreach ([
            [['username', 'password'], ['one', 'StrongPass1!']],
            [['username', 'full_name', 'password', 'username'], ['one', 'One', 'StrongPass1!', 'two']],
            [['username', 'full_name', 'password', 'typo'], ['one', 'One', 'StrongPass1!', 'x']],
        ] as $rows) {
            $this->artisan('users:import', ['file' => $this->csv($rows)])->assertExitCode(2);
        }
        $valid = $this->csv([['username', 'full_name', 'password'], ['one', 'One', 'StrongPass1!']]);
        foreach ([0, 1001, 'nope', '1.5'] as $chunk) {
            $this->artisan('users:import', ['file' => $valid, '--chunk' => $chunk])->assertExitCode(2);
        }
        $this->artisan('users:import', ['file' => $this->unusedPath()])->assertExitCode(2);
        $this->artisan('users:import', ['file' => $valid, '--errors' => $valid])->assertExitCode(2);
        $this->assertStringContainsString('StrongPass1!', file_get_contents($valid));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_handles_blank_lines_bad_column_counts_and_default_email(): void
    {
        config(['app.url' => 'https://crm.example.test']);
        $file = $this->csv([
            ['username', 'full_name', 'password'],
            [],
            ['short', 'Short'],
            ['good', 'Good', 'StrongPass1!'],
        ]);
        $this->artisan('users:import', ['file' => $file])
            ->expectsOutputToContain('Processed: 2 | Created: 1 | Skipped: 0 | Rejected: 1')->assertExitCode(1);
        $this->assertDatabaseHas('users', ['username' => 'good', 'email' => 'good@crm.example.test']);
    }

    public function test_imports_a_large_file_in_multiple_batches(): void
    {
        $rows = [['username', 'full_name', 'password', 'email']];
        for ($i = 0; $i < 525; $i++) {
            $rows[] = ['scale'.$i, 'Scale '.$i, 'StrongPass1!', 'scale'.$i.'@example.test'];
        }
        $file = $this->csv($rows);
        $this->artisan('users:import', ['file' => $file, '--chunk' => 100])
            ->expectsOutputToContain('Processed: 525 | Created: 525 | Skipped: 0 | Rejected: 0')->assertSuccessful();
        $this->assertDatabaseCount('users', 525);
        $this->assertDatabaseHas('users', ['username' => 'scale524']);
    }

    public function test_fatal_errors_retain_completed_accounts_and_do_not_expose_exception_credentials(): void
    {
        User::creating(function (User $user): void {
            if ($user->username === 'stop') {
                throw new \InvalidArgumentException('SQL binding: SensitiveCredential123!');
            }
        });
        $file = $this->csv([
            ['username', 'full_name', 'password'],
            ['first', 'First', 'StrongPass1!'],
            ['stop', 'Stop', 'StrongPass1!'],
            ['last', 'Last', 'StrongPass1!'],
        ]);

        $this->assertSame(1, Artisan::call('users:import', ['file' => $file, '--chunk' => 1]));
        $output = Artisan::output();
        $this->assertStringContainsString('Import stopped.', $output);
        $this->assertStringNotContainsString('SensitiveCredential123!', $output);
        $this->assertDatabaseHas('users', ['username' => 'first']);
        $this->assertDatabaseMissing('users', ['username' => 'stop']);
        $this->assertDatabaseMissing('users', ['username' => 'last']);
    }

    public function test_rejects_unsafe_hash_lengths_and_invalid_booleans_and_limits_console_errors(): void
    {
        $rows = [['username', 'full_name', 'password', 'auto_vici_login']];
        for ($i = 0; $i < 12; $i++) {
            $rows[] = ['invalid'.$i, 'Invalid', 'StrongPass1!', 'perhaps'];
        }
        $rows[] = ['long', 'Long', str_repeat('é', 36).'Aa1', 'false'];
        $rows[] = ['valid', 'Valid', 'StrongPass1!', 'false'];

        $this->assertSame(1, Artisan::call('users:import', ['file' => $this->csv($rows)]));
        $output = Artisan::output();
        $this->assertSame(10, substr_count($output, 'Record '));
        $this->assertStringContainsString('Only the first 10 row errors', $output);
        $this->assertStringContainsString('Created: 1 | Skipped: 0 | Rejected: 13', $output);
        $this->assertDatabaseCount('users', 1);
    }

    private function csv(array $rows, bool $bom = false): string
    {
        $file = tempnam(sys_get_temp_dir(), 'crm-users-test-');
        $this->files[] = $file;
        $stream = fopen($file, 'wb');
        if ($bom) {
            fwrite($stream, "\xEF\xBB\xBF");
        }
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        fclose($stream);

        return $file;
    }

    private function unusedPath(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'crm-users-errors-');
        unlink($file);
        $this->files[] = $file;

        return $file;
    }
}
