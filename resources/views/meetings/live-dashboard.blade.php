@extends('layouts.app')

@section('title', 'Live Dashboard - ' . $meeting->title)

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-4">Live Dashboard</h1>
    <p>Meeting: {{ $meeting->title }}</p>
    <p>Session Status: {{ $session->status->value }}</p>
</div>
@endsection
