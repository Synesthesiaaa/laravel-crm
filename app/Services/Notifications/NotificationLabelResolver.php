<?php

namespace App\Services\Notifications;

use App\Models\AttendanceStatusType;
use App\Models\Campaign;
use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationLabelResolver
{
    /** @var array<string, string>|null */
    private ?array $campaigns = null;

    /** @var array<string, string>|null */
    private ?array $forms = null;

    /** @var array<string, string>|null */
    private ?array $statuses = null;

    /** @var array<string, string>|null */
    private ?array $users = null;

    public function campaign(?string $code): string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return 'Campaign';
        }

        $this->campaigns ??= Campaign::query()
            ->where('is_active', true)
            ->pluck('name', 'code')
            ->mapWithKeys(static fn (mixed $name, mixed $campaignCode): array => [
                strtolower(trim((string) $campaignCode)) => trim((string) $name),
            ])
            ->filter()
            ->all();

        foreach ((array) config('campaigns.fallback', []) as $fallbackCode => $fallback) {
            $fallbackKey = strtolower(trim((string) $fallbackCode));
            if (! isset($this->campaigns[$fallbackKey]) && is_array($fallback)) {
                $name = trim((string) ($fallback['name'] ?? ''));
                if ($name !== '') {
                    $this->campaigns[$fallbackKey] = $name;
                }
            } elseif (is_array($fallback)) {
                $name = trim((string) ($fallback['name'] ?? ''));
                $current = trim((string) ($this->campaigns[$fallbackKey] ?? ''));
                if ($name !== '' && ($current === '' || strcasecmp($current, (string) $fallbackCode) === 0)) {
                    $this->campaigns[$fallbackKey] = $name;
                }
            }
        }

        $name = $this->campaigns[strtolower($code)] ?? null;

        return $name !== null && strcasecmp($name, $code) !== 0 ? $name : 'Campaign';
    }

    public function form(?string $code, ?string $campaignCode = null): string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return 'Form activity';
        }

        $this->forms ??= $this->buildFormLabelMap();
        $cacheKey = strtolower(($campaignCode ?? '').'|'.$code);
        $name = $this->forms[$cacheKey] ?? null;

        if ($name === null && ($campaignCode === null || $campaignCode === '')) {
            foreach ($this->forms as $key => $label) {
                if (str_ends_with($key, '|'.strtolower($code)) && $label !== 'Form activity') {
                    $name = $label;

                    break;
                }
            }
        }

        return $name ?? 'Form activity';
    }

    public function status(?string $status, ?int $statusTypeId = null): string
    {
        if ($statusTypeId !== null) {
            $this->statuses ??= AttendanceStatusType::query()
                ->where('is_active', true)
                ->pluck('label', 'id')
                ->map(static fn (mixed $label): string => trim((string) $label))
                ->filter()
                ->all();
            if (isset($this->statuses[$statusTypeId])) {
                return $this->statuses[$statusTypeId];
            }
        }

        $value = trim((string) $status);
        if ($value === '') {
            return 'Status update';
        }

        return match (strtoupper($value)) {
            'RECORDED' => 'Recorded',
            'LOGIN' => 'Login',
            'LOGOUT' => 'Logout',
            'SUCCESS', 'SUCCEEDED', 'OK', 'COMPLETED', 'COMPLETE' => 'Successful',
            'PENDING', 'WAITING' => 'Pending',
            'FAIL', 'FAILED' => 'Failed',
            'ERROR' => 'Error',
            'REJECT', 'REJECTED' => 'Rejected',
            default => str_contains($value, ' ') ? Str::headline($value) : 'Status update',
        };
    }

    public function attendanceAction(?string $direction): string
    {
        return match ($direction) {
            'start' => 'Started',
            'end' => 'Ended',
            default => 'Recorded',
        };
    }

    public function user(?int $userId): string
    {
        if ($userId === null) {
            return 'Supervisor';
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return 'Supervisor';
        }

        return $this->displayName($user);
    }

    public function agent(?string $identifier): string
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            return 'Agent';
        }

        $this->users ??= $this->buildUserAliasMap();

        return $this->users[strtolower($identifier)] ?? 'Agent';
    }

    /**
     * @return list<string>
     */
    public function aliases(User $user): array
    {
        $values = [
            $user->full_name,
            $user->name,
            $user->username,
            $user->vici_user,
        ];
        $aliases = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $aliases[strtolower($value)] = $value;
            }
        }

        return array_values($aliases);
    }

    public function recipient(?string $recipientType): string
    {
        return match (strtoupper(trim((string) $recipientType))) {
            'USER' => 'Direct message',
            'USER_GROUP' => 'Agent group',
            'CAMPAIGN' => 'Campaign team',
            default => 'Team notification',
        };
    }

    /**
     * @return array<string, string>
     */
    private function buildUserAliasMap(): array
    {
        $map = [];
        User::query()
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'full_name', 'username', 'vici_user'])
            ->each(function (User $user) use (&$map): void {
                $name = $this->displayName($user);
                foreach ([$user->full_name, $user->name, $user->username, $user->vici_user] as $alias) {
                    $alias = trim((string) $alias);
                    if ($alias !== '') {
                        $map[strtolower($alias)] = $name;
                    }
                }
            });

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function buildFormLabelMap(): array
    {
        $labels = [];
        Form::query()
            ->where('is_active', true)
            ->get(['campaign_code', 'form_code', 'name'])
            ->each(function (Form $form) use (&$labels): void {
                $code = trim((string) $form->form_code);
                if ($code === '') {
                    return;
                }

                $name = trim((string) $form->name);
                $labels[strtolower((string) $form->campaign_code.'|'.$code)] = $name !== '' && strcasecmp($name, $code) !== 0
                    ? $name
                    : 'Form activity';
            });

        foreach ((array) config('campaigns.fallback', []) as $fallbackCampaign => $fallback) {
            $fallbackForms = is_array($fallback) ? ($fallback['forms'] ?? []) : [];
            foreach ((array) $fallbackForms as $fallbackCode => $fallbackForm) {
                $fallbackCode = trim((string) $fallbackCode);
                $name = is_array($fallbackForm) ? trim((string) ($fallbackForm['name'] ?? '')) : '';
                if ($fallbackCode === '' || $name === '' || strcasecmp($name, $fallbackCode) === 0) {
                    continue;
                }

                $key = strtolower((string) $fallbackCampaign.'|'.$fallbackCode);
                if (! isset($labels[$key]) || $labels[$key] === 'Form activity') {
                    $labels[$key] = $name;
                }
            }
        }

        return $labels;
    }

    private function displayName(User $user): string
    {
        foreach ([$user->full_name, $user->name, $user->username] as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return 'Agent';
    }
}
