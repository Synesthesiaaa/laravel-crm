@extends('layouts.app')

@section('title', 'Call History')
@section('header-icon')<x-icon name="clipboard-document-list" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Call History')

@section('content')
<nav class="mb-4 text-sm text-[var(--color-on-surface-dim)]" aria-label="Breadcrumb">
    <span class="text-[var(--color-on-surface-muted)]">Call History</span>
</nav>

@include('records.partials.call-history-panel', ['campaign' => $campaign, 'personal' => true])
@endsection
