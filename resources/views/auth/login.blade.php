@extends('layouts.guest')

@section('content')
<h2 class="text-xl font-bold text-gray-900 mb-6">{{ __('auth.login') }}</h2>

@if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus class="focus-primary w-full rounded-lg border border-gray-300 px-4 py-2 text-sm outline-none">
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.password_label') }}</label>
        <input type="password" id="password" name="password" required class="focus-primary w-full rounded-lg border border-gray-300 px-4 py-2 text-sm outline-none">
    </div>

    <button type="submit" class="btn-primary w-full text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">{{ __('auth.login') }}</button>
</form>

<div class="relative my-4">
    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
    <div class="relative flex justify-center text-xs"><span class="bg-white px-2 text-gray-500">{{ __('auth.or_continue_with') }}</span></div>
</div>
@include('auth.partials.social-buttons')
@endsection
