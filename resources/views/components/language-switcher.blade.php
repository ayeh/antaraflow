@php
    $locales = ['en' => 'English', 'ms' => 'Bahasa Melayu'];
    $current = app()->getLocale();
    $currentLabel = $locales[$current] ?? 'English';
@endphp

<details class="relative group" x-data @click.outside="$el.removeAttribute('open')">
    <summary
        class="flex items-center gap-1.5 px-2.5 h-8 rounded-lg cursor-pointer list-none
               text-slate-500 dark:text-slate-400 text-sm
               hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors
               [&::-webkit-details-marker]:hidden"
        title="{{ __('Language') }}"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
        </svg>
        <span class="hidden sm:inline">{{ strtoupper($current) }}</span>
    </summary>
    <div class="absolute right-0 mt-2 w-44 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-lg z-50 py-1">
        @foreach($locales as $code => $label)
            <a href="{{ route('locale.switch', $code) }}"
               class="flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors
                      {{ $current === $code ? 'text-violet-600 dark:text-violet-400 font-medium' : 'text-gray-700 dark:text-gray-300' }}">
                <span>{{ $label }}</span>
                @if($current === $code)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                @endif
            </a>
        @endforeach
    </div>
</details>
