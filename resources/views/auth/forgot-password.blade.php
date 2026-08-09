@extends('layouts.guest')

@section('content')
<h2 class="text-xl font-bold text-gray-900 mb-2">{{ __('auth.forgot_password') }}</h2>
<p class="text-sm text-gray-500 mb-6">{{ __('auth.forgot_password_description') }}</p>

@if(session('status'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
        {{ session('status') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('password.email') }}" class="space-y-4">
    @csrf

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus class="focus-primary w-full rounded-lg border border-gray-300 px-4 py-2 text-sm outline-none">
    </div>

    <button type="submit" class="btn-primary w-full text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">{{ __('auth.send_reset_link') }}</button>
</form>

<p class="mt-4 text-center text-sm text-gray-500">
    <a href="{{ route('login') }}" class="link-primary font-medium hover:opacity-80">{{ __('auth.back_to_login') }}</a>
</p>
@endsection
