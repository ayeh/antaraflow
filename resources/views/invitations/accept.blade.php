@extends('layouts.guest')

@section('content')
@if (! $invitation || ! $valid)
    <h2 class="text-xl font-bold text-gray-900 mb-3">Invitation unavailable</h2>
    <p class="text-sm text-gray-600 mb-6">This invitation is invalid, has expired, or has already been accepted.</p>
    <a href="{{ route('login') }}" class="link-primary text-sm font-medium hover:opacity-80">Go to login</a>
@else
    <h2 class="text-xl font-bold text-gray-900 mb-2">Join {{ $invitation->organization->name }}</h2>
    <p class="text-sm text-gray-600 mb-6">You've been invited to join as a <strong>{{ ucfirst($invitation->role->value) }}</strong>.</p>

    @if ($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @auth
        @if (auth()->user()->email === $invitation->email)
            <form method="POST" action="{{ route('invitations.accept', ['token' => $invitation->token]) }}">
                @csrf
                <p class="text-sm text-gray-600 mb-4">You're signed in as <strong>{{ auth()->user()->email }}</strong>.</p>
                <button type="submit" class="btn-primary w-full text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Accept invitation</button>
            </form>
        @else
            <p class="text-sm text-gray-600 mb-4">This invitation was sent to <strong>{{ $invitation->email }}</strong>, but you're signed in as <strong>{{ auth()->user()->email }}</strong>.</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="link-primary text-sm font-medium hover:opacity-80">Sign out to continue</button>
            </form>
        @endif
    @else
        @if ($existingUser)
            <p class="text-sm text-gray-600 mb-4">An account already exists for <strong>{{ $invitation->email }}</strong>. Please sign in to accept.</p>
            <a href="{{ route('login') }}" class="btn-primary inline-block w-full text-center text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Sign in</a>
        @else
            <form method="POST" action="{{ route('invitations.accept', ['token' => $invitation->token]) }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" value="{{ $invitation->email }}" disabled class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-500 outline-none">
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus-primary outline-none">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password" required class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus-primary outline-none">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus-primary outline-none">
                </div>

                <button type="submit" class="btn-primary w-full text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Create account &amp; join</button>
            </form>
        @endif
    @endauth
@endif
@endsection
