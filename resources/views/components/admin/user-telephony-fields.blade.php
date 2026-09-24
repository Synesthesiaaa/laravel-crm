@props([
    'user' => null,
    'campaignOptions' => [],
])

@php
    $suffix = $user?->getKey() ?? 'new';
    $isEditing = $user !== null;
@endphp

<div class="mt-5 space-y-5 border-t border-[var(--color-border)] pt-5">
    <section>
        <div class="mb-3">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-[var(--color-on-surface-muted)]">VICIdial agent credentials</h4>
            <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Used when the CRM signs the staff member into VICIdial.</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.input name="vici_user" label="VICIdial Username"
                :value="old('vici_user', $user?->vici_user)"
                help="Must match the VICIdial agent username" />
            <x-form.input name="vici_pass" type="password" label="VICIdial Password"
                autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                :help="$isEditing ? 'Leave blank to keep current' : 'Must match the VICIdial agent password'" />
        </div>
    </section>

    <section class="border-t border-[var(--color-border)] pt-5">
        <div class="mb-3">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-[var(--color-on-surface-muted)]">WebRTC / SIP</h4>
            <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Optional browser-calling credentials that must match the configured SIP endpoint and server authentication.</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.input name="extension" label="SIP Extension"
                :value="old('extension', $user?->extension)"
                help="e.g. 6001 — must match the configured SIP endpoint" />
            <x-form.input name="sip_password" type="password" label="SIP Password"
                autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                :help="$isEditing ? 'Leave blank to keep current' : 'Minimum 4 characters — must match the server auth password'" />
        </div>
    </section>

    <section class="border-t border-[var(--color-border)] pt-5">
        <div class="mb-3">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-[var(--color-on-surface-muted)]">CRM telephony auto-start</h4>
            <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Controls the default campaign and automatic VICIdial bootstrap behavior. Automatic login requires <code class="text-[11px]">VICI_AUTO_BOOTSTRAP</code> to be enabled.</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="form-field sm:col-span-2">
                <label class="form-label" for="default_campaign_{{ $suffix }}">Default campaign</label>
                <select id="default_campaign_{{ $suffix }}" name="default_campaign" class="form-select">
                    <option value="">— Use login / session campaign —</option>
                    @foreach($campaignOptions as $code => $cfg)
                        <option value="{{ $code }}" @selected(old('default_campaign', $user?->default_campaign) === $code)>
                            {{ $cfg['name'] ?? $code }}
                        </option>
                    @endforeach
                </select>
                <p class="form-help">Fallback campaign when staging VICIdial after CRM sign-in.</p>
            </div>
            <div class="form-field flex items-end pb-1">
                <input type="hidden" name="auto_vici_login" value="0">
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="auto_vici_login" value="1" class="rounded border-[var(--color-border)]"
                        @checked(old('auto_vici_login', $user?->auto_vici_login ?? false)) />
                    Auto VICIdial login
                </label>
            </div>
            <div class="form-field flex items-end pb-1">
                <input type="hidden" name="default_blended" value="0">
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="default_blended" value="1" class="rounded border-[var(--color-border)]"
                        @checked(old('default_blended', $user?->default_blended ?? true)) />
                    Default blended
                </label>
            </div>
            <div class="form-field col-span-full sm:col-span-2">
                <label class="form-label" for="default_ingroups_{{ $suffix }}">Default in-groups</label>
                <textarea id="default_ingroups_{{ $suffix }}" name="default_ingroups" class="form-textarea" rows="2"
                    placeholder="Space or comma separated, e.g. SALES SUPPORT">{{ old('default_ingroups', $user?->default_ingroups) }}</textarea>
            </div>
        </div>
    </section>
</div>
