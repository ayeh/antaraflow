@extends('layouts.guest')

@section('content')
<h2 class="text-xl font-bold text-gray-900 mb-6">{{ __('auth.reset_password') }}</h2>

@if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('password.update') }}" class="space-y-4">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus class="focus-primary w-full rounded-lg border border-gray-300 px-4 py-2 text-sm outline-none">
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.new_password') }}</label>
        <input type="password" id="password" name="password" required class="focus-primary w-full rounded-lg border border-gray-300 px-4 py-2 text-sm outline-none">
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.confirm_new_password') }}</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required class="focus-primary w-full rounded-lg border border-gray-300 px-4 py-2 text-sm outline-none">
    </div>

    <button type="submit" class="btn-primary w-full text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">{{ __('auth.reset_password') }}</button>
</form>
@endsection
