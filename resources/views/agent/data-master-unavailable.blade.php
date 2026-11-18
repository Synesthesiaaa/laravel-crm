@extends('layouts.app')
@section('title', 'Data Master unavailable')
@section('header-title', 'Data Master')
@section('content')
<x-page-header title="Form records are unavailable" />
<x-alert type="warning" class="mb-4">Ask your administrator to check storage for this form, or select another permitted form.</x-alert>
<form method="GET" action="{{ route('agent.data-master.index') }}" class="flex flex-wrap items-end gap-4 mb-4">
    <x-form.select name="type" label="Form" :options="$forms->pluck('name', 'form_code')->all()" :selected="$type" :empty="false" />
    <button type="submit" class="btn-secondary">Load form</button>
</form>
<a href="{{ route('dashboard') }}" class="btn-ghost">Return to dashboard</a>
@endsection
