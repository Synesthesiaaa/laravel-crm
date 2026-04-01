<?php

namespace App\Services;

use App\Models\User;
use App\Services\Telephony\VicidialCredentialSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        protected VicidialCredentialSyncService $credentialSync,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            return User::create([
                'username' => $data['username'],
                'name' => $data['name'] ?? $data['full_name'] ?? $data['username'],
                'full_name' => $data['full_name'],
                'email' => $data['email'] ?? ($data['username'].'@'.parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'local'),
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'vici_user' => $data['vici_user'] ?? null,
                'vici_pass' => $data['vici_pass'] ?? null,
                'extension' => $data['extension'] ?? null,
                'sip_password' => $data['sip_password'] ?? null,
                'default_campaign' => ! empty($data['default_campaign']) ? (string) $data['default_campaign'] : null,
                'auto_vici_login' => (bool) ($data['auto_vici_login'] ?? false),
                'default_blended' => (bool) ($data['default_blended'] ?? true),
                'default_ingroups' => isset($data['default_ingroups']) && trim((string) $data['default_ingroups']) !== ''
                    ? trim((string) $data['default_ingroups'])
                    : null,
            ]);
        });

        // Push credentials to ViciDial asynchronously (best-effort, never block the UI)
        try {
            $this->credentialSync->syncOnCreate($user);
        } catch (\Throwable) {
            // Swallow – ViciDial sync failure must not block CRM user creation
        }

        return $user;
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        $user = DB::transaction(function () use ($user, $data): User {
            $user->username = $data['username'];
            $user->full_name = $data['full_name'];
            $user->role = $data['role'];
            $user->vici_user = $data['vici_user'] ?? null;
            $user->extension = $data['extension'] ?? null;
            $user->default_campaign = ! empty($data['default_campaign']) ? (string) $data['default_campaign'] : null;
            $user->auto_vici_login = (bool) ($data['auto_vici_login'] ?? false);
            $user->default_blended = (bool) ($data['default_blended'] ?? true);
            $user->default_ingroups = isset($data['default_ingroups']) && trim((string) $data['default_ingroups']) !== ''
                ? trim((string) $data['default_ingroups'])
                : null;

            if (! empty($data['vici_pass'])) {
                $user->vici_pass = $data['vici_pass'];
            }
            if (! empty($data['sip_password'])) {
                $user->sip_password = $data['sip_password'];
            }
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();

            return $user;
        });

        // Push updated credentials to ViciDial (best-effort, never block the UI)
        try {
            $this->credentialSync->syncOnUpdate($user);
        } catch (\Throwable) {
            // Swallow – ViciDial sync failure must not block CRM user update
        }

        return $user;
    }

    public function delete(User $user, User $requestingUser): bool
    {
        if ($user->id === $requestingUser->id) {
            return false;
        }
        $user->delete();

        return true;
    }
}
