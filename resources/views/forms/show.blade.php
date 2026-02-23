@extends('layouts.app')

@section('title', $formName . ' - ' . $campaignName)

@section('header-icon')
    <span class="mr-3 text-indigo-600">📝</span>
@endsection

@section('header-title')
    {{ $formName }} Form
@endsection

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h1 class="text-2xl font-bold text-gray-900">{{ $formName }}</h1>
                <p class="text-gray-500 text-sm mt-1">Fill out the details below for {{ $campaignName }}.</p>
            </div>

            @if (session('success'))
                <div class="mx-6 mt-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mx-6 mt-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('forms.store') }}" method="POST" class="p-6">
                @csrf
                <input type="hidden" name="campaign" value="{{ $campaign }}">
                <input type="hidden" name="form_type" value="{{ $formType }}">
                <input type="hidden" name="lead_id" value="{{ $leadId ?? '' }}">
                <input type="hidden" name="phone_number" value="{{ $phoneNumber ?? '' }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase tracking-tight mb-1">Request ID</label>
                        <input type="text" name="request_id" value="{{ $prefill['request_id'] ?? '' }}" readonly class="w-full px-3 py-2 border border-gray-200 rounded bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase tracking-tight mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="date" required value="{{ $prefill['date'] ?? date('Y-m-d') }}" class="w-full px-3 py-2 border border-gray-200 rounded">
                    </div>
                </div>

                @if (!empty($viciFields))
                <div class="mb-8 p-6 bg-pink-50/50 border border-pink-100 rounded-xl">
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4">VICIdial Lead Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach ($viciFields as $field)
                            @if (!in_array($field['name'], ['request_id', 'date', 'agent']))
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-tight mb-1">
                                    {{ $field['label'] }}
                                    @if($field['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <input type="{{ $field['type'] === 'number' ? 'text' : $field['type'] }}" name="{{ $field['name'] }}"
                                       value="{{ $prefill[$field['name']] ?? '' }}"
                                       class="w-full px-3 py-2 border border-gray-200 rounded"
                                       @if($field['required'] ?? false) required @endif>
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="mb-8 p-6 border border-gray-100 rounded-xl">
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4">{{ $formName }} Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach ($campaignFields as $field)
                            <div class="@if(($field['field_width'] ?? '') === 'full') md:col-span-2 @endif">
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-tight mb-1">
                                    {{ $field['label'] }}
                                    @if($field['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                @if(($field['type'] ?? 'text') === 'textarea')
                                    <textarea name="{{ $field['name'] }}" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded" @if($field['required'] ?? false) required @endif>{{ $prefill[$field['name']] ?? '' }}</textarea>
                                @elseif(($field['type'] ?? 'text') === 'select')
                                    <select name="{{ $field['name'] }}" class="w-full px-3 py-2 border border-gray-200 rounded" @if($field['required'] ?? false) required @endif>
                                        <option value="">-- Select --</option>
                                        @foreach(($field['options'] ?? []) as $opt)
                                            <option value="{{ is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt }}" {{ ($prefill[$field['name']] ?? '') == (is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt) ? 'selected' : '' }}>{{ is_array($opt) ? ($opt['label'] ?? $opt['value'] ?? '') : $opt }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}"
                                           value="{{ $prefill[$field['name']] ?? '' }}"
                                           class="w-full px-3 py-2 border border-gray-200 rounded"
                                           @if($field['required'] ?? false) required @endif>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">Save</button>
                    <a href="{{ route('dashboard') }}" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
