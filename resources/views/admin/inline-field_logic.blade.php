<x-page-header title="Field Logic"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Field Logic' => null]" />

@if(session('success'))
    <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
@endif
<x-validation-errors />

{{-- Form selector --}}
<div class="md-card mb-6">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.field-logic.index') }}" class="flex flex-wrap gap-4 items-end">
            <div class="form-field">
                <label class="form-label">Form</label>
                <select name="form" class="form-input max-w-xs">
                    @foreach($forms as $code => $config)
                        <option value="{{ $code }}" {{ $formType === $code ? 'selected' : '' }}>{{ $config['name'] ?? $code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn-primary">
                    <x-icon name="funnel" class="w-4 h-4" />
                    Load
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Add field --}}
<div class="md-card mb-6">
    <div class="px-6 py-4 border-b border-[var(--color-border)]">
        <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Add field</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.field-logic.store') }}" class="space-y-4" x-data="{ submitting: false, ft: @js(old('field_type', 'text')) }" @submit="submitting = true">
            @csrf
            <input type="hidden" name="campaign_code" value="{{ $campaign }}">
            <input type="hidden" name="form_type" value="{{ $formType }}">
            <div class="flex flex-wrap gap-4 items-end">
            <div class="form-field">
                <label class="form-label">Field name</label>
                <input type="text" name="field_name" value="{{ old('field_name') }}" required class="form-input" placeholder="column_name" pattern="[a-zA-Z0-9_]+" title="Letters, numbers, underscores only">
            </div>
            <div class="form-field">
                <label class="form-label">Label</label>
                <input type="text" name="field_label" value="{{ old('field_label') }}" required class="form-input" placeholder="Display Label">
            </div>
            <div class="form-field">
                <label class="form-label">Type</label>
                <select name="field_type" class="form-input" x-model="ft">
                    <option value="text">Text</option>
                    <option value="textarea">Textarea</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="select">Select</option>
                    <option value="multiselect">Multi-select (checkboxes)</option>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label">Width</label>
                <select name="field_width" class="form-input">
                    <option value="full">Full</option>
                    <option value="half">Half</option>
                    <option value="third">Third</option>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label">Order</label>
                <input type="number" name="field_order" class="form-input w-24" value="" placeholder="auto" min="0" step="1">
            </div>
            <div class="form-field flex items-center gap-2 pt-6">
                <input type="checkbox" name="is_required" value="1" id="add_req" class="rounded border-gray-300">
                <label for="add_req" class="text-sm text-[var(--color-on-surface)]">Required</label>
            </div>
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="plus" class="w-4 h-4" />
                    Add
                </button>
            </div>
            </div>
            <div class="form-field max-w-xl" x-show="ft === 'select' || ft === 'multiselect'" x-cloak>
                <label class="form-label">Options <span class="text-[var(--color-on-surface-muted)] font-normal">(one per line)</span></label>
                <textarea name="options" rows="4" class="form-textarea font-mono text-sm" placeholder="Option A&#10;Option B&#10;Option C">{{ old('options') }}</textarea>
                <p class="form-help mt-1">Required for single select and multi-select fields.</p>
            </div>
        </form>
    </div>
</div>

{{-- Fields table --}}
<div class="md-card overflow-hidden">
    <x-table.index caption="Form fields">
        <x-table.head :columns="[
            ['label' => 'Order'],
            ['label' => 'Name'],
            ['label' => 'Label'],
            ['label' => 'Type'],
            ['label' => 'Width'],
            ['label' => 'Required'],
            ['label' => 'Actions', 'align' => 'right'],
        ]" />
        <tbody>
            @forelse($fields as $f)
                <tr class="border-b border-[var(--color-border)]">
                    <td class="py-3 px-4 text-[var(--color-on-surface-dim)]">{{ $f->field_order }}</td>
                    <td class="py-3 px-4 font-mono text-sm">{{ $f->field_name }}</td>
                    <td class="py-3 px-4">{{ $f->field_label }}</td>
                    <td class="py-3 px-4">{{ $f->field_type }}</td>
                    <td class="py-3 px-4">{{ $f->field_width ?? 'full' }}</td>
                    <td class="py-3 px-4">
                        <x-badge :type="$f->is_required ? 'info' : 'inactive'">
                            {{ $f->is_required ? 'Yes' : 'No' }}
                        </x-badge>
                    </td>
                    <td class="py-3 px-4">
                        <div class="table-actions flex justify-end gap-2"
                             x-data="{ async doDelete(el) {
                                const ok = await Alpine.store('confirm').ask('Delete field?', 'Remove this field? This cannot be undone.');
                                if (ok) el.submit();
                             }}">
                            <button type="button"
                                    class="btn-secondary text-xs px-2 py-1"
                                    @click="$store.modal.show('edit-field-logic', {{ json_encode(array_merge($f->only(['id','field_name','field_label','field_type','is_required','field_order','field_width']), ['options_text' => $f->optionsTextForAdmin()])) }})">
                                <x-icon name="pencil" class="w-3.5 h-3.5" />
                                Edit
                            </button>
                            <form method="POST" action="{{ route('admin.field-logic.destroy') }}" x-ref="delForm" class="inline">
                                @csrf
                                <input type="hidden" name="id" value="{{ $f->id }}">
                                <button type="button" class="btn-danger text-xs px-2 py-1"
                                        @click="doDelete($refs.delForm)">
                                    <x-icon name="trash" class="w-3.5 h-3.5" />
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-8 px-4 text-center text-[var(--color-on-surface-dim)]">No fields. Add one above.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table.index>
</div>

{{-- Edit field modal --}}
<x-modal name="edit-field-logic" title="Edit field" maxWidth="lg">
    <div x-data="{
        edit: { id: null, field_name: '', field_label: '', field_type: 'text', field_width: 'full', field_order: 0, is_required: false, options_text: '' }
    }" x-effect="$store.modal.is('edit-field-logic') && $store.modal.data && $store.modal.data.id && (edit = { id: $store.modal.data.id, field_name: $store.modal.data.field_name || '', field_label: $store.modal.data.field_label || '', field_type: $store.modal.data.field_type || 'text', field_width: $store.modal.data.field_width || 'full', field_order: $store.modal.data.field_order ?? 0, is_required: !!$store.modal.data.is_required, options_text: $store.modal.data.options_text || '' })">
        <form method="POST" x-show="edit.id"
              :action="'{{ url('admin/field-logic') }}/' + edit.id"
              x-data="{ submitting: false }"
              @submit="submitting = true">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="form-field sm:col-span-2">
                    <label class="form-label">Field name</label>
                    <input type="text" name="field_name" class="form-input" x-model="edit.field_name" pattern="[a-zA-Z0-9_]+" required>
                </div>
                <div class="form-field sm:col-span-2">
                    <label class="form-label">Label</label>
                    <input type="text" name="field_label" class="form-input" x-model="edit.field_label" required>
                </div>
                <div class="form-field">
                    <label class="form-label">Type</label>
                    <select name="field_type" class="form-input" x-model="edit.field_type">
                        <option value="text">Text</option>
                        <option value="textarea">Textarea</option>
                        <option value="number">Number</option>
                        <option value="date">Date</option>
                        <option value="select">Select</option>
                        <option value="multiselect">Multi-select (checkboxes)</option>
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label">Width</label>
                    <select name="field_width" class="form-input" x-model="edit.field_width">
                        <option value="full">Full</option>
                        <option value="half">Half</option>
                        <option value="third">Third</option>
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label">Order</label>
                    <input type="number" name="field_order" class="form-input" x-model.number="edit.field_order" min="0" step="1">
                </div>
                <div class="form-field flex items-center gap-2 pt-6">
                    <input type="checkbox" name="is_required" value="1" id="edit_req"
                           x-model="edit.is_required"
                           class="rounded border-gray-300">
                    <label for="edit_req" class="text-sm text-[var(--color-on-surface)]">Required</label>
                </div>
                <div class="form-field sm:col-span-2" x-show="edit.field_type === 'select' || edit.field_type === 'multiselect'">
                    <label class="form-label">Options <span class="text-[var(--color-on-surface-muted)] font-normal">(one per line)</span></label>
                    <textarea name="options" rows="4" class="form-textarea font-mono text-sm" x-model="edit.options_text" placeholder="Option A&#10;Option B"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="$store.modal.hide()">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="check" class="w-4 h-4" />
                    Update
                </button>
            </div>
        </form>
    </div>
</x-modal>
