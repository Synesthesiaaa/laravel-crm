@extends('layouts.app')

@section('title', 'Edit Capture Record - Admin')
@section('header-icon')<x-icon name="pencil-square" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Edit Capture Record')

@section('content')
<x-page-header title="Edit Capture Record"
    description="Update captured details for this lead interaction."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Capture Records' => route('admin.capture-records.index'), 'Edit' => null]" />

<x-validation-errors />

@if(session('error'))
    <x-alert type="error" class="mb-4">
        {{ session('error') }}
    </x-alert>
@endif

@php
    $captureData = is_array($record->capture_data) ? $record->capture_data : [];
    $checkedValues = ['1', 'true', 'yes', 'on'];
@endphp

<div class="md-card max-w-5xl">
    <div class="p-6">
        <form method="POST" action="{{ route('admin.capture-records.update', ['record' => $record->id]) }}">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <x-form.input
                    name="lead_id"
                    label="Lead ID"
                    :value="$record->lead_id"
                    placeholder="Optional lead id"
                />
                <x-form.input
                    name="phone_number"
                    label="Phone Number"
                    :value="$record->phone_number"
                    placeholder="Optional phone number"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                @foreach($fields as $field)
                    @php
                        $fieldType = strtolower((string) ($field->field_type ?: 'text'));
                        $fieldName = 'capture_data['.$field->field_key.']';
                        $fieldValue = data_get(old('capture_data', []), $field->field_key, $captureData[$field->field_key] ?? null);
                        $isChecked = in_array(strtolower(trim((string) $fieldValue)), $checkedValues, true);
                        $options = is_array($field->options) ? $field->options : [];
                    @endphp

                    @if($fieldType === 'textarea')
                        <x-form.textarea
                            :name="$fieldName"
                            :label="$field->field_label"
                            :value="$fieldValue"
                            :placeholder="$field->placeholder"
                            :required="(bool) $field->is_required"
                            rows="3"
                        />
                    @elseif($fieldType === 'select')
                        <x-form.select
                            :name="$fieldName"
                            :label="$field->field_label"
                            :selected="$fieldValue"
                            :required="(bool) $field->is_required"
                            :empty="'-- Select --'"
                        >
                            @foreach($options as $option)
                                <option value="{{ $option }}" @selected((string) $fieldValue === (string) $option)>{{ $option }}</option>
                            @endforeach
                        </x-form.select>
                    @elseif($fieldType === 'checkbox')
                        <div class="form-field mt-6">
                            <x-form.checkbox
                                :name="$fieldName"
                                :label="$field->field_label"
                                :checked="$isChecked"
                            />
                        </div>
                    @else
                        <x-form.input
                            :name="$fieldName"
                            :label="$field->field_label"
                            :value="$fieldValue"
                            :type="in_array($fieldType, ['text', 'number', 'email', 'tel', 'date'], true) ? $fieldType : 'text'"
                            :placeholder="$field->placeholder"
                            :required="(bool) $field->is_required"
                        />
                    @endif
                @endforeach
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    Update
                </button>
                <a href="{{ route('admin.capture-records.index') }}" class="btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
