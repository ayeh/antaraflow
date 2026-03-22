@php
$organizationItems = [
    ['label' => 'Organizations',        'route' => route('organizations.index'),             'active' => request()->routeIs('organizations.index', 'organizations.create')],
    ['label' => 'Meeting Templates',    'route' => route('meeting-templates.index'),         'active' => request()->routeIs('meeting-templates.*')],
    ['label' => 'Meeting Series',       'route' => route('meeting-series.index'),            'active' => request()->routeIs('meeting-series.*')],
    ['label' => 'Export Templates',     'route' => route('settings.export-templates.index'), 'active' => request()->routeIs('settings.export-templates.*')],
    ['label' => 'Extraction Templates', 'route' => route('extraction-templates.index'),      'active' => request()->routeIs('extraction-templates.*')],
    ['label' => 'Tags',                 'route' => route('tags.index'),                      'active' => request()->routeIs('tags.*')],
    ['label' => 'Attendee Groups',      'route' => route('attendee-groups.index'),           'active' => request()->routeIs('attendee-groups.*')],
    ['label' => 'Integrations',         'route' => route('settings.integrations'),           'active' => request()->routeIs('settings.integrations')],
    ['label' => 'AI Providers',         'route' => route('ai-provider-configs.index'),       'active' => request()->routeIs('ai-provider-configs.*')],
    ['label' => 'Webhooks',             'route' => route('webhooks.index'),                  'active' => request()->routeIs('webhooks.*')],
];

$accountItems = [
    ['label' => 'Subscription', 'route' => route('subscription.index'), 'active' => request()->routeIs('subscription.*')],
    ['label' => 'Usage',        'route' => route('usage.index'),        'active' => request()->routeIs('usage.*')],
    ['label' => 'Audit Log',    'route' => route('audit-log.index'),    'active' => request()->routeIs('audit-log.*')],
];

$allSections = [
    ['label' => 'Organization', 'items' => $organizationItems],
    ['label' => 'Account',      'items' => $accountItems],
];

$globalIndex = 0;
@endphp

<div
    x-show="activeFlyout === 'settings'"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-x-2"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 -translate-x-2"
    @click.outside="activeFlyout = null"
    :style="{ left: sidebarCollapsed ? '68px' : '236px', top: '12px', maxHeight: 'calc(100vh - 24px)' }"
    class="fixed z-40 w-60 rounded-2xl
           bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700
           shadow-xl py-3 overflow-y-auto"
>
    @foreach($allSections as $sectionIndex => $section)
        {{-- Section header --}}
        <div class="px-4 {{ $sectionIndex === 0 ? 'pb-1' : 'pt-3 pb-1' }} {{ $sectionIndex > 0 ? 'mt-1 border-t border-slate-100 dark:border-slate-700' : '' }}">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                {{ $section['label'] }}
            </span>
        </div>

        {{-- Section items --}}
        @foreach($section['items'] as $item)
            <a
                href="{{ $item['route'] }}"
                class="flex items-center gap-3 mx-2 px-3 py-2 rounded-xl text-sm transition-all duration-150
                       {{ $item['active']
                           ? 'bg-violet-50 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 font-medium'
                           : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }}"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                style="transition-delay: {{ $globalIndex * 20 }}ms"
            >
                {{ $item['label'] }}
            </a>
            @php $globalIndex++ @endphp
        @endforeach
    @endforeach
</div>
