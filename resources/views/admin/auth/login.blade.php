<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Login — antaraFLOW</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-900">
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h1 class="text-3xl font-bold text-white">antaraFLOW</h1>
                <p class="mt-2 text-sm text-slate-400">Super Admin Panel</p>
            </div>

            <div class="bg-slate-800 rounded-xl border border-slate-700 p-8">
                @if($errors->any())
                    <div class="mb-6 bg-red-900/20 border border-red-800 text-red-300 px-4 py-3 rounded-lg text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-6">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-300">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                            class="mt-1 block w-full rounded-lg bg-slate-700 border-slate-600 text-white placeholder-slate-400 focus:ring-violet-500 focus:border-violet-500 px-4 py-2.5">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
                        <input type="password" id="password" name="password" required
                            class="mt-1 block w-full rounded-lg bg-slate-700 border-slate-600 text-white placeholder-slate-400 focus:ring-violet-500 focus:border-violet-500 px-4 py-2.5">
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember"
                            class="rounded bg-slate-700 border-slate-600 text-violet-600 focus:ring-violet-500">
                        <label for="remember" class="ml-2 text-sm text-slate-400">Remember me</label>
                    </div>

                    <button type="submit"
                        class="w-full bg-violet-600 text-white py-2.5 rounded-lg font-medium hover:bg-violet-700 transition-colors">
                        Sign In
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
