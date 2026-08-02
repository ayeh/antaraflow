@php
$sections = [
    [
        'label' => __('nav.settings_workspace'),
        'items' => [
            [
                'label'  => __('nav.organizations'),
                'desc'   => __('nav.organizations_desc'),
                'route'  => route('organizations.index'),
                'active' => request()->routeIs('organizations.index', 'organizations.create'),
                'icon'   => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
            ],
        ],
    ],
    [
        'label' => __('nav.settings_meetings'),
        'items' => [
            [
                'label'  => __('nav.meeting_templates'),
                'desc'   => __('nav.meeting_templates_desc'),
                'route'  => route('meeting-templates.index'),
                'active' => request()->routeIs('meeting-templates.*'),
                'icon'   => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
            ],
            [
                'label'  => __('nav.meeting_series'),
                'desc'   => __('nav.meeting_series_desc'),
                'route'  => route('meeting-series.index'),
                'active' => request()->routeIs('meeting-series.*'),
                'icon'   => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99',
            ],
            [
                'label'  => __('nav.tags'),
                'desc'   => __('nav.tags_desc'),
                'route'  => route('tags.index'),
                'active' => request()->routeIs('tags.*'),
                'icon'   => 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L9.568 3zM6 6h.008v.008H6V6z',
            ],
            [
                'label'  => __('nav.attendee_groups'),
                'desc'   => __('nav.attendee_groups_desc'),
                'route'  => route('attendee-groups.index'),
                'active' => request()->routeIs('attendee-groups.*'),
                'icon'   => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
            ],
        ],
    ],
    [
        'label' => __('nav.settings_templates'),
        'items' => [
            [
                'label'  => __('nav.export_templates'),
                'desc'   => __('nav.export_templates_desc'),
                'route'  => route('settings.export-templates.index'),
                'active' => request()->routeIs('settings.export-templates.*'),
                'icon'   => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3',
            ],
            [
                'label'  => __('nav.extraction_templates'),
                'desc'   => __('nav.extraction_templates_desc'),
                'route'  => route('extraction-templates.index'),
                'active' => request()->routeIs('extraction-templates.*'),
                'icon'   => 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z',
            ],
        ],
    ],
    [
        'label' => __('nav.settings_connect'),
        'items' => [
            [
                'label'  => __('nav.integrations'),
                'desc'   => __('nav.integrations_desc'),
                'route'  => route('settings.integrations'),
                'active' => request()->routeIs('settings.integrations'),
                'icon'   => 'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.39 48.39 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.401.604-.401.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.959.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z',
            ],
            [
                'label'  => __('nav.ai_providers'),
                'desc'   => __('nav.ai_providers_desc'),
                'route'  => route('ai-provider-configs.index'),
                'active' => request()->routeIs('ai-provider-configs.*'),
                'icon'   => 'M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z',
            ],
            [
                'label'  => __('nav.webhooks'),
                'desc'   => __('nav.webhooks_desc'),
                'route'  => route('webhooks.index'),
                'active' => request()->routeIs('webhooks.*'),
                'icon'   => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
            ],
        ],
    ],
    [
        'label' => __('nav.account'),
        'items' => [
            [
                'label'  => __('nav.subscription'),
                'desc'   => __('nav.subscription_desc'),
                'route'  => route('subscription.index'),
                'active' => request()->routeIs('subscription.*'),
                'icon'   => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
            ],
            [
                'label'  => __('nav.usage'),
                'desc'   => __('nav.usage_desc'),
                'route'  => route('usage.index'),
                'active' => request()->routeIs('usage.*'),
                'icon'   => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
            ],
            [
                'label'  => __('nav.audit_log'),
                'desc'   => __('nav.audit_log_desc'),
                'route'  => route('audit-log.index'),
                'active' => request()->routeIs('audit-log.*'),
                'icon'   => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z',
            ],
        ],
    ],
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
    class="fixed z-40 w-72 rounded-2xl
           bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700
           shadow-xl py-3 overflow-y-auto"
>
    @foreach($sections as $sectionIndex => $section)
        {{-- Section header --}}
        <div class="px-4 {{ $sectionIndex === 0 ? 'pb-1' : 'pt-3 pb-1' }} {{ $sectionIndex > 0 ? 'mt-1 border-t border-slate-100 dark:border-slate-700' : '' }}">
            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">
                {{ $section['label'] }}
            </span>
        </div>

        {{-- Section items --}}
        @foreach($section['items'] as $item)
            <a
                href="{{ $item['route'] }}"
                class="flex items-center gap-3 mx-2 px-3 py-2.5 rounded-xl transition-all duration-150
                       {{ $item['active']
                           ? 'bg-violet-50 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300'
                           : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60' }}"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                style="transition-delay: {{ $globalIndex * 18 }}ms"
            >
                {{-- Icon --}}
                <span class="shrink-0 flex items-center justify-center w-8 h-8 rounded-lg
                             {{ $item['active']
                                 ? 'bg-violet-100 dark:bg-violet-800/50'
                                 : 'bg-slate-100 dark:bg-slate-700' }}">
                    <svg class="w-4 h-4 {{ $item['active'] ? 'text-violet-600 dark:text-violet-400' : 'text-slate-500 dark:text-slate-400' }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                    </svg>
                </span>

                {{-- Label + description --}}
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium leading-tight {{ $item['active'] ? 'text-violet-700 dark:text-violet-300' : 'text-slate-800 dark:text-slate-200' }}">
                        {{ $item['label'] }}
                    </span>
                    <span class="block text-[11px] leading-tight mt-0.5 {{ $item['active'] ? 'text-violet-500 dark:text-violet-400' : 'text-slate-400 dark:text-slate-500' }}">
                        {{ $item['desc'] }}
                    </span>
                </span>
            </a>
            @php $globalIndex++ @endphp
        @endforeach
    @endforeach
</div>
