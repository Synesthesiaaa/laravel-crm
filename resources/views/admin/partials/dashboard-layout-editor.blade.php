    @if($user->isAdmin())
        @php
            $storedDashboardSections = collect($dashboardLayout['sections'] ?? [])
                ->sortBy('order')
                ->keys()
                ->values()
                ->all();
            $storedVisibleDashboardSections = collect($dashboardLayout['sections'] ?? [])
                ->filter(fn ($section) => ($section['visible'] ?? false) === true)
                ->keys()
                ->values()
                ->all();
            $savedDashboardSections = old('section_order', $storedDashboardSections);
            $visibleDashboardSections = old('visible_sections', $storedVisibleDashboardSections);
        @endphp
        <x-admin.create-panel title="Customize user dashboard" description="Choose campaign dashboard sections and amounts." :open="$errors->any()">
        <div x-data="adminDashboardLayoutEditor({
            sections: @js($savedDashboardSections),
            visible: @js($visibleDashboardSections),
            labels: @js($dashboardSections),
        })">
            <form method="POST" action="{{ route('admin.dashboard-layout.update') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="campaign_code" value="{{ $campaign }}">
                <fieldset class="space-y-2" x-data="{ amountsEnabled: @js((bool) old('amounts.enabled', data_get($dashboardLayout, 'amounts.enabled', true))) }">
                    <legend class="text-sm font-semibold text-[var(--color-on-surface)]">Dashboard amounts</legend>
                    <p class="text-xs text-[var(--color-on-surface-dim)]">Turn off all amounts for this campaign, or choose the monetary displays to include. Sales counts remain visible.</p>
                    @foreach(\App\Services\DashboardLayoutService::amountDefinitions() as $amountKey => $amountLabel)
                        <label class="flex min-h-11 items-center gap-3 rounded-lg border border-[var(--color-border)] px-3 py-2"
                               @if($amountKey !== 'enabled') x-show="amountsEnabled" @endif>
                            <input type="hidden" name="amounts[{{ $amountKey }}]" value="0">
                            <input type="checkbox" name="amounts[{{ $amountKey }}]" value="1" class="form-checkbox"
                                   @checked(old('amounts.'.$amountKey, data_get($dashboardLayout, 'amounts.'.$amountKey, true)))
                                   @if($amountKey === 'enabled') x-model="amountsEnabled" @endif>
                            <span class="text-sm">{{ $amountLabel }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <div class="space-y-2">
                    <template x-for="(section, index) in sections" :key="section">
                        <div class="flex items-center gap-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2">
                            <input type="hidden" name="section_order[]" :value="section">
                            <input type="checkbox" name="visible_sections[]" :value="section" :id="'dashboard-section-' + section" x-model="visible" class="form-checkbox">
                            <label :for="'dashboard-section-' + section" class="flex-1 text-sm text-[var(--color-on-surface)]" x-text="labels[section]"></label>
                            <div class="flex items-center gap-1">
                                <button type="button" class="btn-icon" @click="move(index, -1)" :disabled="index === 0" :aria-label="'Move ' + labels[section] + ' up'">↑</button>
                                <button type="button" class="btn-icon" @click="move(index, 1)" :disabled="index === sections.length - 1" :aria-label="'Move ' + labels[section] + ' down'">↓</button>
                            </div>
                        </div>
                    </template>
                </div>

                @if($errors->any())
                    <div class="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger-muted)] p-3 text-xs text-[var(--color-danger-fg)]" role="alert">
                        <p class="font-semibold">Review the dashboard settings</p>
                        <ul class="mt-1 list-disc pl-4 space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-3 flex-wrap pt-2">
                    <p class="text-xs text-[var(--color-on-surface-dim)]">Changes apply to users viewing {{ $campaignName }}.</p>
                    <button type="submit" class="btn-primary">Apply dashboard layout</button>
                </div>
            </form>
        </div>
        </x-admin.create-panel>
    @endif

