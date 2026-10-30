@php
    $importMaxBytes = $userImportMaxBytes ?? \App\Services\Imports\UserImportService::uploadLimitBytes();
    $importMaxLabel = rtrim(rtrim(number_format($importMaxBytes / 1048576, 1, '.', ''), '0'), '.').' MB';
@endphp
<div id="user-import-panel" class="mb-6" x-data="userImportPanel(@js(['runs' => $userImports ?? [], 'uploadUrl' => route('admin.user-imports.store'), 'maxBytes' => $importMaxBytes, 'maxLabel' => $importMaxLabel]))">
    <x-admin.panel title="Import users from CSV" description="Create many staff accounts from one file. Preview every row before adding users.">
        <x-slot:actions>
            <a href="{{ route('admin.user-imports.template') }}" class="btn-secondary text-sm">Download CSV template</a>
        </x-slot:actions>

        <form method="POST" action="{{ route('admin.user-imports.store') }}" enctype="multipart/form-data" @submit.prevent="upload($el)" :aria-busy="uploading">
            @csrf
            <div class="grid min-w-0 gap-5 lg:grid-cols-2 lg:items-start">
                <div class="min-w-0">
                    <label for="users-import-csv" class="form-label">CSV file</label>
                    <input id="users-import-csv" name="csv" type="file" accept=".csv,text/csv" required
                        class="form-input block w-full min-w-0 text-sm" :disabled="busy"
                        aria-describedby="users-import-help users-import-error" :aria-invalid="Boolean(error)" @change="error = ''">
                    <p id="users-import-help" class="form-help mt-2">UTF-8 CSV, up to {{ $importMaxLabel }} on this server. Required columns: <span class="font-medium text-[var(--color-on-surface)]">username, full_name, password</span>.</p>
                </div>
                <div class="min-w-0 text-sm leading-6 text-[var(--color-on-surface-muted)]">
                    <p>Existing usernames are skipped. Replace the template’s sample passwords. Use at least 8 characters, uppercase, lowercase, and a number.</p>
                    <label class="mt-3 flex min-h-11 cursor-pointer items-start gap-3 text-[var(--color-on-surface)]">
                        <input type="checkbox" name="allow_privileged_roles" value="1" x-model="allowPrivileged" :disabled="busy" class="mt-1 h-4 w-4 shrink-0 accent-[var(--color-primary)]">
                        <span>Allow roles above Agent<span class="block text-xs text-[var(--color-on-surface-muted)]">Enable only if the CSV intentionally includes Team Leader, Admin, or Super Admin accounts.</span></span>
                    </label>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="submit" class="btn-primary" :disabled="busy">
                    <span x-text="uploading ? 'Uploading… ' + uploadPercent + '%' : 'Upload and preview'">Upload and preview</span>
                </button>
                <p class="text-xs leading-5 text-[var(--color-on-surface-muted)]">Preview creates no accounts. VICIdial provisioning stays off.</p>
            </div>
        </form>

        <div id="users-import-error" class="mt-4 flex flex-wrap items-center gap-3 text-sm text-[var(--color-danger-fg)]" x-show="error" x-cloak>
            <p role="alert" x-text="error"></p>
            <button type="button" class="btn-secondary" x-show="run" :disabled="busy" @click="poll(true)">Retry status</button>
        </div>

        <template x-if="run">
            <div class="mt-6 border-t border-[var(--color-border)] pt-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h4 class="break-words text-sm font-semibold text-[var(--color-on-surface)]" x-text="run.file_name"></h4>
                        <p class="mt-1 text-sm text-[var(--color-on-surface-muted)]" role="status" aria-live="polite"><span class="font-medium text-[var(--color-on-surface)]" x-text="statusLabel()"></span><span> — </span><span x-text="statusMessage()"></span></p>
                    </div>
                    <button type="button" class="btn-ghost shrink-0" :disabled="busy" @click="poll(true)" aria-label="Refresh import status">Refresh status</button>
                </div>
                <progress class="mt-4 h-2 w-full accent-[var(--color-primary)]" max="100" :value="run.percent" aria-label="CSV processing progress" x-show="run.active"></progress>
                <dl class="mt-4 grid grid-cols-2 gap-x-5 gap-y-4 border-y border-[var(--color-border)] py-4 sm:grid-cols-4">
                    <div><dt class="text-xs text-[var(--color-on-surface-muted)]">Rows checked</dt><dd class="mt-1 text-lg font-semibold tabular-nums" x-text="number(run.processed)"></dd></div>
                    <div><dt class="text-xs text-[var(--color-on-surface-muted)]" x-text="run.phase === 'preview' ? 'Eligible users' : 'Users created'"></dt><dd class="mt-1 text-lg font-semibold tabular-nums" x-text="number(run.accepted)"></dd></div>
                    <div><dt class="text-xs text-[var(--color-on-surface-muted)]">Skipped</dt><dd class="mt-1 text-lg font-semibold tabular-nums" x-text="number(run.skipped)"></dd></div>
                    <div><dt class="text-xs text-[var(--color-on-surface-muted)]">Rejected</dt><dd class="mt-1 text-lg font-semibold tabular-nums" x-text="number(run.rejected)"></dd></div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" class="btn-primary" x-show="run.can_start" :disabled="busy" @click="startImport()" x-text="busy ? 'Starting…' : 'Import ' + number(run.accepted) + ' eligible users'"></button>
                    <a class="btn-secondary" x-show="run.rejected > 0" :href="run.urls.report">Download row errors</a>
                    <button type="button" class="btn-secondary" x-show="run.can_cancel" :disabled="busy" @click="cancelImport()" x-text="run.phase === 'preview' ? 'Discard upload' : 'Cancel import'"></button>
                    <a class="btn-primary" href="{{ route('admin.users.index') }}" x-show="run.status === 'completed'">Refresh user list</a>
                </div>
                <details class="mt-4 text-sm" x-show="run.errors?.length > 0">
                    <summary class="min-h-11 cursor-pointer py-3 font-medium text-[var(--color-on-surface)]">View row issues (first 10)</summary>
                    <ul class="space-y-2 text-[var(--color-on-surface-muted)]">
                        <template x-for="issue in run.errors" :key="issue.record"><li class="break-words"><span class="font-medium text-[var(--color-on-surface)]" x-text="'Record ' + issue.record + ': '"></span><span x-text="issue.errors"></span></li></template>
                    </ul>
                </details>
            </div>
        </template>

        <div class="mt-5 border-t border-[var(--color-border)] pt-4" x-show="runs.length > 1" x-cloak>
            <p class="mb-2 text-xs font-medium text-[var(--color-on-surface-muted)]">Your recent imports</p>
            <ul class="flex flex-col gap-1">
                <template x-for="item in runs" :key="item.id">
                    <li><button type="button" class="btn-ghost w-full justify-start gap-2 text-left text-sm" @click="selectRun(item)" :disabled="busy" :aria-current="run?.id === item.id ? 'true' : null"><span class="min-w-0 truncate" x-text="item.file_name"></span><span class="shrink-0 text-xs text-[var(--color-on-surface-muted)]" x-text="statusLabel(item)"></span></button></li>
                </template>
            </ul>
        </div>
        <noscript><p class="mt-4 text-sm">Enable JavaScript to upload and track CSV imports. The individual user forms below remain available.</p></noscript>
    </x-admin.panel>
</div>
