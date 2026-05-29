@extends('layouts.app')

@section('title', 'Edit Record - Admin')

@section('header-icon')
    <x-icon name="pencil" class="w-5 h-5 text-[var(--color-primary)]" />
@endsection

@section('header-title')
    Edit Record
@endsection

@section('content')
    @if(session('error'))
        <x-alert type="error" class="mb-4">{{ session('error') }}</x-alert>
    @endif
    <x-validation-errors />
    <x-page-header title="Edit Record"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Data Master' => route('admin.data-master.index', ['type' => $type]), 'Edit Record' => null]" />

    <div class="md-card max-w-2xl overflow-hidden">
        <div class="p-6">
            <form method="POST" action="{{ route('admin.data-master.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_table" value="{{ $tableName }}">
                <input type="hidden" name="_id" value="{{ $record->id ?? $record['id'] }}">
                <input type="hidden" name="_type" value="{{ $type }}">
                @foreach($columns as $col)
                    @if(!in_array($col, ['id', 'created_at', 'updated_at'], true))
                        <div class="form-field">
                            <label class="form-label">{{ $headers[$col] ?? $col }}</label>
                            <input type="text" name="{{ $col }}" value="{{ is_object($record) ? ($record->$col ?? '') : ($record[$col] ?? '') }}" class="form-input">
                        </div>
                    @endif
                @endforeach
                <div class="form-actions-bottom pt-2">
                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        Update
                    </button>
                    <a href="{{ route('admin.data-master.index', ['type' => $type]) }}" class="btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
