@extends('layouts.app')

@php $isEdit = (bool) $lead; @endphp

@section('title', $isEdit ? 'Edit Lead #' . $lead->id : 'New Lead')
@section('header-icon')<x-icon name="user" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', $isEdit ? 'Edit Lead' : 'New Lead')

@section('content')
<x-page-header :title="$isEdit ? 'Edit Lead #' . $lead->id : 'New Lead'"
    :description="'List: ' . $list->name"
    :breadcrumbs="[
        'Admin' => route('admin.dashboard'),
        'Lead Lists' => route('admin.leads.lists.index', ['campaign' => $list->campaign_code]),
        $list->name => route('admin.leads.lists.show', $list),
        'Leads' => route('admin.leads.leads.index', $list),
        ($isEdit ? 'Edit' : 'New') => null,
    ]" />

<x-validation-errors />

<form method="POST"
      action="{{ $isEdit ? route('admin.leads.leads.update', ['list' => $list, 'lead' => $lead]) : route('admin.leads.leads.store', $list) }}"
      x-data="{ submitting: false }" @submit="submitting = true">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="md-card">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($fields as $field)
                    @php
                        $key = $field->field_key;
                        $isStandard = in_array($key, [
                            'vendor_lead_code','source_id','phone_code','phone_number','alt_phone','title',
                            'first_name','middle_initial','last_name','address1','address2','address3',
                            'city','state','province','postal_code','country','gender','date_of_birth',
                            'email','security_phrase','comments','status',
                        ], true);
                        $isCountable = in_array($key, ['called_count','last_called_at'], true);
                        if ($isCountable) continue;

                        if ($isStandard) {
                            $inputName = $key;
                            $value = old($key, $lead?->{$key});
                        } else {
                            $inputName = 'custom_fields[' . $key . ']';
                            $value = old('custom_fields.' . $key, data_get($lead?->custom_fields ?? [], $key));
                        }

                        $required = $key === 'phone_number';
                    @endphp

                    @if($field->field_type === 'textarea')
                        <div class="md:col-span-3">
                            <label class="text-xs text-[var(--color-on-surface-dim)]">{{ $field->field_label }}</label>
                            <textarea name="{{ $inputName }}" class="form-textarea w-full" rows="3" @if($required) required @endif>{{ $value }}</textarea>
                        </div>
                    @elseif($field->field_type === 'select' && is_array($field->field_options))
                        <div>
                            <label class="text-xs text-[var(--color-on-surface-dim)]">{{ $field->field_label }}</label>
                            <select name="{{ $inputName }}" class="form-select w-full">
                                <option value="">--</option>
                                @foreach($field->field_options as $opt)
                                    <option value="{{ $opt }}" @selected((string) $value === (string) $opt)>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <x-form.input
                            :name="$inputName"
                            :label="$field->field_label"
                            :value="$value"
                            :type="in_array($field->field_type, ['number','date','email']) ? $field->field_type : 'text'"
                            :required="$required"
                        />
                    @endif
                @endforeach

                <x-form.input name="status" label="Status" :value="old('status', $lead?->status ?? 'NEW')" />
                <div class="flex items-center gap-2">
                    <x-form.checkbox name="enabled" label="Enabled" :checked="old('enabled', $lead?->enabled ?? true)" />
                </div>
            </div>

            <div class="mt-6 flex gap-2">
                <button type="submit" class="btn-primary" :disabled="submitting">
                    <x-icon name="check" class="w-4 h-4" />
                    <span x-text="submitting ? 'Saving...' : '{{ $isEdit ? 'Update' : 'Create' }}'">{{ $isEdit ? 'Update' : 'Create' }}</span>
                </button>
                <a href="{{ route('admin.leads.leads.index', $list) }}" class="btn-ghost">Cancel</a>
            </div>
        </div>
    </div>
</form>
@endsection
