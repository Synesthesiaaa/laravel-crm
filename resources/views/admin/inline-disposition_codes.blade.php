<x-page-header title="Disposition Codes"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Disposition Codes' => null]" />

<x-validation-errors />

{{-- Campaign filter --}}
<div class="md-card mb-6">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.disposition-codes.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.select name="campaign" label="Campaign"
                :options="collect($campaigns)->mapWithKeys(fn($v,$k) => [$k => $v['name'] ?? $k])->all()"
                :selected="$filterCampaign"
                empty="Global (all campaigns)" />
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn-secondary">
                    <x-icon name="funnel" class="w-4 h-4" />
                    Filter
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Add code form --}}
<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Add Disposition Code</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.disposition-codes.store') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <input type="hidden" name="campaign_code" value="{{ $filterCampaign }}">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <x-form.input name="code"  label="Code"   required placeholder="e.g. SALE" />
                <x-form.input name="label" label="Label"  required placeholder="Sale" />
                <x-form.input name="sort_order" type="number" label="Sort Order" value="0" />
                <div class="form-field">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn-primary" :disabled="submitting">
                        <x-icon name="plus" class="w-4 h-4" />
                        Add
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Codes table --}}
<x-table.index caption="Disposition codes">
    <x-table.head :columns="[
        ['label' => 'Code'],
        ['label' => 'Label'],
        ['label' => 'Order'],
        ['label' => 'Status'],
        ['label' => 'Actions', 'align' => 'right'],
    ]" />
    <tbody>
        @forelse($codes as $code)
            <tr>
                <td><span class="font-mono font-semibold text-sm text-[var(--color-on-surface)]">{{ $code->code }}</span></td>
                <td>{{ $code->label }}</td>
                <td>{{ $code->sort_order }}</td>
                <td>
                    <x-badge :type="$code->is_active ? 'active' : 'inactive'">
                        {{ $code->is_active ? 'Active' : 'Inactive' }}
                    </x-badge>
                </td>
                <td>
                    <div class="table-actions" x-data="{ editOpen: false }">
                        <button class="btn-secondary text-xs px-2 py-1" @click="editOpen = !editOpen">
                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                            <span x-text="editOpen ? 'Cancel' : 'Edit'">Edit</span>
                        </button>
                        <div x-data="{ async del(form) {
                            const ok = await Alpine.store('confirm').ask('Delete code?', 'Remove disposition code {{ $code->code }}.');
                            if (ok) form.submit();
                        }}">
                            <form method="POST" action="{{ route('admin.disposition-codes.destroy') }}" x-ref="delFormD{{ $code->id }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $code->id }}">
                                <button type="button" class="btn-danger text-xs px-2 py-1"
                                        @click="del($refs['delFormD{{ $code->id }}'])">
                                    <x-icon name="trash" class="w-3.5 h-3.5" />
                                    Delete
                                </button>
                            </form>
                        </div>
                        {{-- Inline edit --}}
                        <div x-show="editOpen" x-collapse
                             style="display:none; position: absolute; right: 1rem; top: 100%; z-index: 20; background: var(--color-surface-2); border: 1px solid var(--color-border-strong); border-radius: 10px; padding: 1rem; min-width: 28rem; box-shadow: var(--shadow-3);">
                            <form method="POST" action="{{ route('admin.disposition-codes.update', $code->id) }}"
                                  x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                @method('PUT')
                                <div class="grid grid-cols-3 gap-3">
                                    <x-form.input name="code"  label="Code"  :value="$code->code" />
                                    <x-form.input name="label" label="Label" :value="$code->label" />
                                    <x-form.input name="sort_order" type="number" label="Order" :value="$code->sort_order" />
                                </div>
                                <input type="hidden" name="is_active" value="1">
                                <div class="mt-3">
                                    <button type="submit" class="btn-primary text-sm" :disabled="submitting">
                                        <x-icon name="check" class="w-4 h-4" />
                                        Update
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" message="No disposition codes found." />
        @endforelse
    </tbody>
</x-table.index>
