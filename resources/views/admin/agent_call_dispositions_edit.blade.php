@extends('layouts.app')

@section('title', 'Edit Agent Call Record')
@section('header-icon')<x-icon name="pencil" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Edit Agent Call Record')

@section('content')
<x-page-header title="Edit record #{{ $record->id }}"
    :breadcrumbs="[
        'Admin' => route('admin.dashboard'),
        'Agent Call Records' => route('admin.agent-records.index'),
        'Edit' => null,
    ]" />

<div class="md-card p-6 max-w-3xl">
    <form method="POST" action="{{ route('admin.agent-records.update', $record) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="form-field">
            <label class="form-label">Disposition code</label>
            <select name="disposition_code" class="form-select" required>
                @foreach($dispositionCodes as $dc)
                    @php
                        $code = is_array($dc) ? ($dc['code'] ?? '') : ($dc->code ?? '');
                        $label = is_array($dc) ? ($dc['label'] ?? $code) : ($dc->label ?? $code);
                    @endphp
                    <option value="{{ $code }}" @selected($record->disposition_code === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-field">
            <label class="form-label">Disposition label</label>
            <input type="text" name="disposition_label" class="form-input" value="{{ old('disposition_label', $record->disposition_label) }}" />
        </div>

        <div class="form-field">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-textarea" rows="3">{{ old('remarks', $record->remarks) }}</textarea>
        </div>

        <div class="form-field">
            <label class="form-label">Call duration (seconds)</label>
            <input type="number" name="call_duration_seconds" class="form-input w-40" min="0"
                   value="{{ old('call_duration_seconds', $record->call_duration_seconds) }}" />
        </div>

        <h3 class="text-sm font-semibold pt-4">Capture data</h3>
        @php $capture = old('capture', $record->capture_data ?? []); @endphp
        @if(is_array($capture) && count($capture) > 0)
            @foreach($capture as $key => $val)
                <div class="form-field">
                    <label class="form-label">{{ $key }}</label>
                    <input type="text" name="capture[{{ $key }}]" class="form-input" value="{{ is_scalar($val) ? $val : json_encode($val) }}" />
                </div>
            @endforeach
        @else
            <p class="text-sm text-[var(--color-on-surface-muted)]">No capture fields stored for this record.</p>
        @endif

        <div class="flex gap-3 pt-4">
            <button type="submit" class="btn-primary">Save changes</button>
            <a href="{{ route('admin.agent-records.index') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
