<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BrandingService
{
    /**
     * Resolve the branding values used by customer-facing views.
     *
     * @return array{
     *     name: string,
     *     logo_path: ?string,
     *     logo_url: ?string,
     *     favicon_path: ?string,
     *     favicon_url: string,
     *     logo_alt: string
     * }
     */
    public function resolve(): array
    {
        return Cache::remember(
            (string) config('branding.cache_key'),
            now()->addSeconds((int) config('branding.cache_ttl', 300)),
            function (): array {
                $values = SystemSetting::query()
                    ->whereIn('setting_key', $this->settingKeys())
                    ->pluck('setting_value', 'setting_key')
                    ->all();

                $name = trim((string) ($values[config('branding.company_name_key')] ?? ''));
                $name = $name !== '' ? $name : $this->defaultCompanyName();
                $logoPath = $this->availablePath($values[config('branding.logo_path_key')] ?? null);
                $faviconPath = $this->availablePath($values[config('branding.favicon_path_key')] ?? null);

                return [
                    'name' => $name,
                    'logo_path' => $logoPath,
                    'logo_url' => $this->assetUrl($logoPath),
                    'favicon_path' => $faviconPath,
                    'favicon_url' => $this->assetUrl($faviconPath)
                        ?? asset((string) config('branding.default_favicon', 'favicon.ico')),
                    'logo_alt' => $name.' logo',
                ];
            },
        );
    }

    /**
     * Persist branding settings and any replacement assets.
     *
     * @param  array{
     *     company_name: string,
     *     logo?: ?UploadedFile,
     *     favicon?: ?UploadedFile
     * }  $data
     */
    public function update(array $data): void
    {
        $keys = $this->settingKeys();
        $before = SystemSetting::query()
            ->whereIn('setting_key', $keys)
            ->pluck('setting_value', 'setting_key')
            ->all();
        $newPaths = [];
        $newValues = [
            config('branding.company_name_key') => trim($data['company_name']),
        ];

        try {
            foreach ([
                'logo' => config('branding.logo_path_key'),
                'favicon' => config('branding.favicon_path_key'),
            ] as $field => $settingKey) {
                $file = $data[$field] ?? null;
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $path = $this->storeUploadedFile($file);
                $newPaths[] = $path;
                $newValues[$settingKey] = $path;
            }

            DB::transaction(function () use ($newValues): void {
                foreach ($newValues as $settingKey => $settingValue) {
                    SystemSetting::query()->updateOrCreate(
                        ['setting_key' => $settingKey],
                        ['setting_value' => $settingValue],
                    );
                }
            });
        } catch (Throwable $exception) {
            $this->deletePaths($newPaths);

            throw $exception;
        }

        $after = SystemSetting::query()
            ->whereIn('setting_key', $keys)
            ->pluck('setting_value', 'setting_key')
            ->all();

        $this->flush();
        $this->deletePaths(array_values(array_diff(
            $this->customPaths($before),
            $this->customPaths($after),
        )));

        if ($before === $after) {
            return;
        }

        $logger = activity('configuration')
            ->event('updated')
            ->withProperties([
                'attributes' => $after,
                'old' => $before,
            ]);

        if (auth()->check()) {
            $logger->causedBy(auth()->user());
        }

        $logger->log('Branding settings updated');
    }

    public function flush(): void
    {
        Cache::forget((string) config('branding.cache_key'));
    }

    /**
     * @return list<string>
     */
    private function settingKeys(): array
    {
        return [
            (string) config('branding.company_name_key'),
            (string) config('branding.logo_path_key'),
            (string) config('branding.favicon_path_key'),
        ];
    }

    private function defaultCompanyName(): string
    {
        $name = trim((string) config('branding.default_name', ''));

        return $name !== '' ? $name : 'CRM';
    }

    private function storeUploadedFile(UploadedFile $file): string
    {
        $extension = strtolower($file->extension());
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs((string) config('branding.path'), $filename, $this->disk());

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store the branding asset.');
        }

        return $path;
    }

    private function availablePath(mixed $path): ?string
    {
        if (! is_string($path) || ! $this->isCustomPath($path)) {
            return null;
        }

        try {
            return Storage::disk($this->disk())->exists($path) ? $path : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function assetUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        try {
            $url = Storage::disk($this->disk())->url($path);

            if (config("filesystems.disks.{$this->disk()}.driver") === 'local') {
                $localPath = parse_url($url, PHP_URL_PATH);

                if (is_string($localPath) && $localPath !== '') {
                    $url = $localPath;
                }
            }

            return $url.(str_contains($url, '?') ? '&' : '?').'v='.rawurlencode(sha1($path));
        } catch (Throwable) {
            return null;
        }
    }

    private function disk(): string
    {
        return (string) config('branding.disk', 'public');
    }

    private function isCustomPath(string $path): bool
    {
        $prefix = trim((string) config('branding.path'), '/').'/';

        return str_starts_with($path, $prefix)
            && $path !== $prefix;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function customPaths(array $values): array
    {
        return array_values(array_filter([
            $values[config('branding.logo_path_key')] ?? null,
            $values[config('branding.favicon_path_key')] ?? null,
        ], fn (mixed $path): bool => is_string($path) && $this->isCustomPath($path)));
    }

    /**
     * @param  list<string>  $paths
     */
    private function deletePaths(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            if (! $this->isCustomPath($path)) {
                continue;
            }

            try {
                Storage::disk($this->disk())->delete($path);
            } catch (Throwable) {
                // Cleanup must never hide a successful settings update.
            }
        }
    }
}
