<section aria-labelledby="data-master-access-title">
    <h2 id="data-master-access-title" class="text-lg font-semibold">Agent Data Master access</h2>
    <p class="form-help mt-2 mb-6">Control which campaign submissions agents can review. Agent access is always read-only.</p>

    <form method="GET" action="{{ route('admin.configuration') }}" data-soft-nav class="flex flex-wrap items-end gap-4 mb-6">
        <input type="hidden" name="tab" value="data-master">
        <x-form.select name="data_master_campaign" label="Campaign" :options="$dataMasterCampaigns->pluck('name', 'code')->all()"
            :selected="$selectedDataMasterCampaign?->code" :empty="false" />
        <button type="submit" class="btn-secondary">Load campaign</button>
    </form>

    @if($selectedDataMasterCampaign)
        <x-validation-errors />
        <form method="POST" action="{{ route('admin.configuration.data-master.update', ['campaign' => $selectedDataMasterCampaign->code]) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <div>
                <h3 class="font-semibold mb-4">{{ $selectedDataMasterCampaign->name }} permissions</h3>
                <div class="space-y-4">
                    <div>
                        <x-form.checkbox name="enabled" label="Enable agent Data Master" :checked="$dataMasterAccess['enabled']" aria-describedby="data-master-enabled-help" />
                        <p id="data-master-enabled-help" class="form-help mt-2">Applies to all agents using this campaign. Disable to remove access on their next request.</p>
                    </div>
                    <div>
                        <x-form.checkbox name="own_submissions_only" label="Own submissions only" :checked="$dataMasterAccess['own_submissions_only']" aria-describedby="data-master-scope-help" />
                        <p id="data-master-scope-help" class="form-help mt-2">On: each agent sees only submissions linked to their account. Off: agents see all users' submissions in the allowed campaign forms.</p>
                    </div>
                </div>
            </div>
            <fieldset>
                <legend class="font-semibold mb-2">Allowed forms</legend>
                <p class="form-help mb-4">Select the forms agents can view and filter. New forms need explicit permission.</p>
                <input type="hidden" name="form_selection_present" value="1">
                <div class="grid gap-3 sm:grid-cols-2">
                    @forelse($dataMasterForms as $form)
                        <label for="data-master-form-{{ $form->id }}" class="flex items-center gap-3 py-2 cursor-pointer">
                            <input id="data-master-form-{{ $form->id }}" type="checkbox" name="form_ids[]" value="{{ $form->id }}"
                                class="w-4 h-4 accent-[var(--color-primary)]"
                                @checked(in_array($form->id, old('form_selection_present') ? old('form_ids', []) : $dataMasterAccess['form_ids']))>
                            <span>{{ $form->name }}</span>
                        </label>
                    @empty
                        <p class="form-help">No active forms. Add an active campaign form before enabling access.</p>
                    @endforelse
                </div>
            </fieldset>
            <p class="form-help">Older records without an account link stay hidden in own-submissions-only mode.</p>
            <div class="form-actions-bottom">
                <button type="submit" class="btn-primary">Save Data Master permissions</button>
            </div>
        </form>
    @else
        <x-alert type="info">Create an active campaign before configuring agent Data Master access.</x-alert>
    @endif
</section>
