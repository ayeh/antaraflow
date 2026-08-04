@if($circulation)
<div class="mt-6 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">
            {{ __('Circulation Monitor') }} · {{ __('Round') }} {{ $circulation->round }}
        </h3>
        <span class="text-xs text-gray-500 dark:text-slate-400">
            {{ __('Deadline') }}: {{ $circulation->deadline_at->format('d M Y, g:i A') }}
        </span>
    </div>

    {{-- Tally bar --}}
    @php
        $total     = $circulation->recipients->count();
        $confirmed = $circulation->recipients->where('response', 'confirmed')->count();
        $amended   = $circulation->recipients->where('response', 'amendment_requested')->count();
        $notOpened = $circulation->recipients->filter(fn ($r) => ($r->open_count ?? 0) === 0)->count();
        $pending   = $total - $confirmed - $amended;
    @endphp

    <div class="px-4 py-3 flex flex-wrap gap-4 text-sm border-b border-gray-100 dark:border-slate-700">
        <span class="text-green-600 dark:text-green-400 font-medium">
            ✓ {{ $confirmed }} {{ __('confirmed') }}
        </span>
        <span class="text-amber-600 dark:text-amber-400 font-medium">
            💬 {{ $amended }} {{ __('amendment') }}
        </span>
        <span class="text-gray-400 dark:text-slate-500">
            ○ {{ $pending }} {{ __('no response') }}
        </span>
        @if($notOpened > 0)
            <span class="text-red-500 dark:text-red-400 font-medium">
                ✕ {{ $notOpened }} {{ __('not opened') }}
            </span>
        @endif
    </div>

    {{-- Recipient list --}}
    <ul class="divide-y divide-gray-100 dark:divide-slate-700">

        {{-- Not opened first (highest urgency) --}}
        @foreach($circulation->recipients->filter(fn ($r) => ($r->open_count ?? 0) === 0) as $recipient)
            <li class="px-4 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $recipient->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-slate-500">{{ $recipient->email }}</p>
                </div>
                <span class="text-xs text-red-500 dark:text-red-400 font-medium">{{ __('Not opened') }}</span>
            </li>
        @endforeach

        {{-- Responded: confirmed or amendment --}}
        @foreach($circulation->recipients->filter(fn ($r) => $r->response !== null) as $recipient)
            <li class="px-4 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $recipient->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-slate-500">
                        {{ $recipient->responded_at?->format('d M Y, g:i A') }}
                    </p>
                </div>
                <span @class([
                    'text-xs font-medium px-2 py-0.5 rounded-full',
                    'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' => $recipient->response === 'confirmed',
                    'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300' => $recipient->response === 'amendment_requested',
                ])>
                    {{ $recipient->response === 'confirmed' ? '✓ ' . __('Confirmed') : '💬 ' . __('Amendment') }}
                </span>
            </li>
        @endforeach

        {{-- Opened but no response yet --}}
        @foreach($circulation->recipients->filter(fn ($r) => ($r->open_count ?? 0) > 0 && $r->response === null) as $recipient)
            <li class="px-4 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $recipient->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-slate-500">{{ __('Opened, awaiting response') }}</p>
                </div>
                <span class="text-xs text-gray-500 dark:text-slate-400">○</span>
            </li>
        @endforeach

    </ul>
</div>
@endif
