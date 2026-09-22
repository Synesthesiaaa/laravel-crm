@extends('layouts.app')

@section('title', 'Call History')
@section('header-icon')<x-icon name="clipboard-document-list" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Call History')

@section('content')
<x-page-header title="Call History" :breadcrumbs="['Call History' => null]" />

@include('records.partials.call-history-panel', ['campaign' => $campaign, 'personal' => $personal])
@endsection
