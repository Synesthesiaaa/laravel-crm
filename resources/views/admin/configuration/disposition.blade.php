            <div class="max-w-3xl space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-[var(--color-on-surface)]">Report Disposition Settings</h2>
                    <p class="mt-1 max-w-2xl text-sm text-[var(--color-on-surface-muted)]">
                        Control whether VICIdial system-generated retry results are counted in report totals shown to supervisors and administrators.
                    </p>
                </div>

                <x-alert type="info">
                    Agent-selectable disposition codes are still managed per campaign from the
                    <a href="{{ route('admin.disposition-codes.index') }}" class="link-primary">Disposition Codes</a> page.
                </x-alert>

                <form method="POST" action="{{ route('admin.configuration.report-dispositions.update') }}" class="space-y-5">
                    @csrf

                    <section class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] p-5 space-y-5">
                        <label class="flex items-start justify-between gap-5">
                            <span>
                                <span class="block text-sm font-semibold text-[var(--color-on-surface)]">Hide system dispositions in reports</span>
                                <span class="mt-1 block text-xs leading-5 text-[var(--color-on-surface-muted)]">
                                    Excludes configured system results from Total Calls, campaign totals, status breakdowns, disposition totals, contact rate, and calls per agent. This is intended for automatic retry statuses that were not final user-handled outcomes.
                                </span>
                            </span>
                            <input type="checkbox"
                                   name="hide_system_dispositions"
                                   value="1"
                                   class="mt-1 h-4 w-4 shrink-0 rounded border-[var(--color-border)]"
                                   @checked(old('hide_system_dispositions', $reportDispositionSettings['hide_system_dispositions'] ?? false))>
                        </label>

                        <div class="form-field">
                            <label class="form-label" for="system-disposition-codes">VICIdial system disposition codes</label>
                            <input id="system-disposition-codes"
                                   name="system_disposition_codes"
                                   type="text"
                                   maxlength="1000"
                                   value="{{ old('system_disposition_codes', $reportDispositionSettings['system_disposition_codes_text'] ?? '') }}"
                                   placeholder="NA, AB"
                                   aria-describedby="system-disposition-codes-help{{ $errors->has('system_disposition_codes') ? ' system-disposition-codes-error' : '' }}"
                                   class="form-input w-full">
                            <p id="system-disposition-codes-help" class="form-help">
                                Enter the statuses your VICIdial setup creates automatically, separated by commas, spaces, semicolons, or new lines. Example: NA, AB. Raw VICIdial output remains available in the report debug area for troubleshooting.
                            </p>
                            @error('system_disposition_codes')
                                <p id="system-disposition-codes-error" role="alert" class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </section>

                    <div>
                        <button type="submit" class="btn-primary">Save Report Disposition Settings</button>
                    </div>
                </form>
            </div>
