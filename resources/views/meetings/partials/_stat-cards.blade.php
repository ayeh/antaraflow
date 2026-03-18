{{-- Usage: @include('meetings.partials._stat-cards', ['stats' => $stats]) --}}
@php
$cards = [
    [
        'label'  => 'Total',
        'count'  => $stats['total'],
        'href'   => route('meetings.index', request()->except(['status', 'page'])),
        'active' => !request('status'),
        'color'  => 'violet',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
    ],
    [
        'label'  => 'Draft',
        'count'  => $stats['draft'],
        'href'   => route('meetings.index', array_merge(request()->except(['status', 'page']), ['status' => 'draft'])),
        'active' => request('status') === 'draft',
        'color'  => 'slate',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
    ],
    [
        'label'  => 'Finalized',
        'count'  => $stats['finalized'],
        'href'   => route('meetings.index', array_merge(request()->except(['status', 'page']), ['status' => 'finalized'])),
        'active' => request('status') === 'finalized',
        'color'  => 'green',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    ],
    [
        'label'  => 'Approved',
        'count'  => $stats['approved'],
        'href'   => route('meetings.index', array_merge(request()->except(['status', 'page']), ['status' => 'approved'])),
        'active' => request('status') === 'approved',
        'color'  => 'amber',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
    ],
];
$colorMap = [
    'violet' => ['bg' => 'bg-violet-100 dark:bg-violet-900/30', 'icon' => 'text-violet-600 dark:text-violet-400', 'ring' => 'ring-2 ring-violet-500', 'hover' => 'hover:border-violet-300 dark:hover:border-violet-700'],
    'slate'  => ['bg' => 'bg-slate-100 dark:bg-slate-700',      'icon' => 'text-slate-500 dark:text-slate-400',   'ring' => 'ring-2 ring-slate-400',  'hover' => 'hover:border-slate-300 dark:hover:border-slate-500'],
    'green'  => ['bg' => 'bg-green-100 dark:bg-green-900/30',   'icon' => 'text-green-600 dark:text-green-400',   'ring' => 'ring-2 ring-green-500',  'hover' => 'hover:border-green-300 dark:hover:border-green-700'],
    'amber'  => ['bg' => 'bg-amber-100 dark:bg-amber-900/30',   'icon' => 'text-amber-600 dark:text-amber-400',   'ring' => 'ring-2 ring-amber-500',  'hover' => 'hover:border-amber-300 dark:hover:border-amber-700'],
];
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    @foreach($cards as $card)
        @php $c = $colorMap[$card['color']]; @endphp
        <a href="{{ $card['href'] }}"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 transition-all hover:shadow-md {{ $c['hover'] }} {{ $card['active'] ? $c['ring'] : '' }}"
           aria-label="{{ $card['label'] }}: {{ $card['count'] }} meetings">
            <div class="flex items-start justify-between mb-3">
                <div class="w-9 h-9 rounded-xl {{ $c['bg'] }} flex items-center justify-center">
                    <svg class="w-5 h-5 {{ $c['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $card['icon'] !!}
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $card['count'] }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $card['label'] }}</div>
        </a>
    @endforeach
</div>
