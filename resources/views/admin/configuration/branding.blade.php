            <div class="max-w-4xl space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-[var(--color-on-surface)]">Company Branding</h2>
                    <p class="mt-1 max-w-2xl text-sm text-[var(--color-on-surface-muted)]">
                        Customize how the CRM is identified across the browser, login screen, sidebar, and dashboard.
                    </p>
                </div>

                @if($errors->any())
                    <div id="branding-errors" role="alert" tabindex="-1" class="rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-sm text-[var(--color-on-surface)]">
                        <p class="font-semibold">There is a problem with your branding changes.</p>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-[var(--color-on-surface-muted)]">
                            @foreach($errors->keys() as $field)
                                <li><a class="link-primary" href="#branding-{{ $field }}">{{ $errors->first($field) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.configuration.branding.update') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <section class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-2)] p-5">
                        <div class="form-field max-w-2xl">
                            <label class="form-label" for="branding-company_name">Company name <span class="text-[var(--color-danger)]">*</span></label>
                            <input id="branding-company_name"
                                   name="company_name"
                                   type="text"
                                   value="{{ old('company_name', $brandingSettings['name'] ?? '') }}"
                                   maxlength="{{ config('branding.max_company_name_length', 120) }}"
                                   required
                                   autocomplete="organization"
                                   aria-describedby="branding-company-name-help{{ $errors->has('company_name') ? ' branding-company_name-error' : '' }}"
                                   class="form-input w-full">
                            <p id="branding-company-name-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Used in page titles, the login screen, dashboard welcome, and sidebar.</p>
                            @error('company_name')
                                <p id="branding-company_name-error" role="alert" class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </section>

                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <section class="rounded-xl border border-[var(--color-border)] p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Company logo</h3>
                                    <p class="mt-1 text-xs text-[var(--color-on-surface-muted)]">Shown on the login screen and sidebar.</p>
                                </div>
                                <x-brand :branding="$brandingSettings" variant="preview" :show-name="false" aria-label="Current company logo preview" />
                            </div>
                            <div class="form-field mt-5">
                                <label class="form-label" for="branding-logo">Upload logo</label>
                                <input id="branding-logo"
                                       name="logo"
                                       type="file"
                                       accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                                       aria-describedby="branding-logo-help{{ $errors->has('logo') ? ' branding-logo-error' : '' }}"
                                       class="form-input w-full cursor-pointer">
                                <p id="branding-logo-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">PNG, JPG, JPEG, or WebP. Maximum {{ number_format(config('branding.max_logo_kilobytes', 5120) / 1024, 1) }} MB.</p>
                                @error('logo')
                                    <p id="branding-logo-error" role="alert" class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </section>

                        <section class="rounded-xl border border-[var(--color-border)] p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Browser tab icon</h3>
                                    <p class="mt-1 text-xs text-[var(--color-on-surface-muted)]">Shown in the browser tab and bookmarks.</p>
                                </div>
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3">
                                    <img src="{{ $brandingSettings['favicon_url'] }}" alt="{{ ($brandingSettings['name'] ?? 'CRM').' favicon' }}" width="40" height="40" class="h-full w-full object-contain" decoding="async">
                                </div>
                            </div>
                            <div class="form-field mt-5">
                                <label class="form-label" for="branding-favicon">Upload favicon</label>
                                <input id="branding-favicon"
                                       name="favicon"
                                       type="file"
                                       accept=".png,.jpg,.jpeg,.webp,.ico,image/png,image/jpeg,image/webp,image/x-icon"
                                       aria-describedby="branding-favicon-help{{ $errors->has('favicon') ? ' branding-favicon-error' : '' }}"
                                       class="form-input w-full cursor-pointer">
                                <p id="branding-favicon-help" class="mt-1 text-xs text-[var(--color-on-surface-dim)]">PNG, JPG, JPEG, WebP, or ICO. Maximum {{ number_format(config('branding.max_favicon_kilobytes', 2048) / 1024, 1) }} MB.</p>
                                @error('favicon')
                                    <p id="branding-favicon-error" role="alert" class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </section>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">Save Branding Changes</button>
                    </div>
                </form>
            </div>
