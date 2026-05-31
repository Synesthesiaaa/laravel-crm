@extends('layouts.app')

@section('title', $formName . ' - ' . $campaignName)
@section('header-icon')<x-icon name="document-text" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', $formName . ' Form')

@section('content')
@include('forms._content')
@endsection
