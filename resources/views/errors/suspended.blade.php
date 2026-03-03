<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Suspended</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-slate-50 dark:bg-slate-900">
    <div class="min-h-full flex items-center justify-center py-12 px-4">
        <div class="max-w-md w-full text-center">
            <div class="text-6xl mb-4">&#x1F6AB;</div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">Account Suspended</h1>
            <p class="text-slate-600 dark:text-slate-400 mb-4">
                Your organization has been suspended by the platform administrator.
            </p>
            @if($reason)
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                    <p class="text-sm text-red-700 dark:text-red-300"><strong>Reason:</strong> {{ $reason }}</p>
                </div>
            @endif
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Please contact support for more information.
            </p>
        </div>
    </div>
</body>
</html>
