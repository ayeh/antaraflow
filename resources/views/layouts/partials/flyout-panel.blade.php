@php
$flyoutGroups = [
    'home' => [
        'title' => 'Home',
        'items' => [
            ['label' => 'Dashboard', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
        ],
    ],
    'meetings' => [
        'title' => 'Meetings',
        'items' => [
            ['label' => 'All Meetings', 'route' => route('meetings.index'),  'active' => request()->routeIs('meetings.index')],
            ['label' => 'New Meeting',  'route' => route('meetings.create'), 'active' => request()->routeIs('meetings.create')],
        ],
    ],
    'tasks' => [
        'title' => 'Tasks',
        'items' => [
            ['label' => 'Action Items', 'route' => route('action-items.dashboard'), 'active' => request()->routeIs('action-items.dashboard')],
        ],
    ],
    'projects' => [
        'title' => 'Projects',
        'items' => [
            ['label' => 'All Projects', 'route' => route('projects.index'),  'active' => request()->routeIs('projects.index')],
            ['label' => 'New Project',  'route' => route('projects.create'), 'active' => request()->routeIs('projects.create')],
        ],
    ],
    'ai' => [
        'title' => 'AI Tools',
        'items' => [],
        'note'  => 'Open a meeting and use the Transcriptions, Extractions, or Chat tabs to access AI features.',
    ],
    'analytics' => [
        'title' => 'Analytics',
        'items' => [
            ['label' => 'Overview', 'route' => route('analytics.index'), 'active' => request()->routeIs('analytics.*')],
        ],
    ],
    'settings' => [
        'title' => 'Settings',
        'items' => [
            ['label' => 'Organizations',      'route' => route('organizations.index'),     'active' => request()->routeIs('organizations.index', 'organizations.create')],
            ['label' => 'Meeting Templates',  'route' => route('meeting-templates.index'), 'active' => request()->routeIs('meeting-templates.*')],
            ['label' => 'Meeting Series',     'route' => route('meeting-series.index'),    'active' => request()->routeIs('meeting-series.*')],
            ['label' => 'Tags',              'route' => route('tags.index'),              'active' => request()->routeIs('tags.*')],
            ['label' => 'Attendee Groups',   'route' => route('attendee-groups.index'),   'active' => request()->routeIs('attendee-groups.*')],
            ['label' => 'AI Providers',      'route' => route('ai-provider-configs.index'), 'active' => request()->routeIs('ai-provider-configs.*')],
            ['label' => 'API Keys',          'route' => route('api-keys.index'),          'active' => request()->routeIs('api-keys.*')],
            ['label' => 'Subscription',      'route' => route('subscription.index'),      'active' => request()->routeIs('subscription.*')],
            ['label' => 'Usage',             'route' => route('usage.index'),             'active' => request()->routeIs('usage.*')],
            ['label' => 'Audit Log',            'route' => route('audit-log.index'),        'active' => request()->routeIs('audit-log.*')],
            ['label' => 'Calendar Connections', 'route' => route('calendar.connections'),   'active' => request()->routeIs('calendar.*')],
        ],
    ],
    'profile' => [
        'title'   => 'Account',
        'items'   => [
            ['label' => 'Edit Profile', 'route' => route('profile.edit'), 'active' => request()->routeIs('profile.edit')],
        ],
        'profile' => true,
    ],
];
@endphp

<div
    x-show="activeFlyout !== null"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-x-2"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 -translate-x-2"
    @click.outside="activeFlyout = null"
    class="fixed left-[68px] z-40 w-60 rounded-2xl
           bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700
           shadow-xl py-3 overflow-y-auto"
    :style="activeFlyout === 'profile' ? 'bottom: 12px; max-height: calc(100vh - 24px)' : 'top: 12px; max-height: calc(100vh - 24px)'"
>
    @foreach($flyoutGroups as $key => $group)
    <div x-show="activeFlyout === '{{ $key }}'">
        {{-- Group title --}}
        <div class="px-4 pb-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                {{ $group['title'] }}
            </span>
        </div>

        {{-- Nav items --}}
        @foreach($group['items'] as $index => $item)
        <a
            href="{{ $item['route'] }}"
            class="flex items-center gap-3 mx-2 px-3 py-2 rounded-xl text-sm transition-all duration-150
                   {{ $item['active']
                       ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 font-medium'
                       : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }}"
            x-show="activeFlyout === '{{ $key }}'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            style="transition-delay: {{ $index * 50 }}ms"
        >
            {{ $item['label'] }}
        </a>
        @endforeach

        {{-- Note (for groups with no direct items) --}}
        @if(!empty($group['note']))
        <p class="mx-4 mt-2 text-xs text-slate-400 dark:text-slate-500 leading-relaxed">
            {{ $group['note'] }}
        </p>
        @endif

        {{-- Profile group --}}
        @if(!empty($group['profile']))
        <div class="px-4 py-2 mb-1">
            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ auth()->user()->name }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
            @if(auth()->user()->currentOrganization)
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ auth()->user()->currentOrganization->name }}</p>
            @endif
        </div>
        <div class="border-t border-slate-100 dark:border-slate-700 pt-2 mx-2">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm
                           text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Logout
                </button>
            </form>
        </div>
        @endif
    </div>
    @endforeach
</div>
