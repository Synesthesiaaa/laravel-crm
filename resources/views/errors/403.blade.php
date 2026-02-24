@extends('errors.layout')
@section('code', '403')
@section('title', 'Access Denied')
@section('description', $exception->getMessage() ?: 'You do not have permission to view this page.')
