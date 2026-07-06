@props(['align' => 'right'])

@php
    $supported = config('locales.supported', ['en' => 'English']);
    $current = app()->getLocale();
    $currentLabel = $supported[$current] ?? $current;
@endphp

{{-- Native <details> disclosure: no JS dependency, works everywhere. --}}
<details {{ $attributes->merge(['class' => 'relative group']) }}
    x-data
    @click.outside="$el.open = false">
    <summary
        class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm cursor-pointer list-none
               text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors
               [&::-webkit-details-marker]:hidden"
        aria-label="{{ __('common.language') }}"
    >
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
        </svg>
        <span>{{ $currentLabel }}</span>
        <svg class="w-3 h-3 shrink-0 text-slate-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </summary>

    <div
        class="absolute z-50 mt-2 min-w-[10rem] rounded-xl bg-white dark:bg-slate-800
               border border-slate-200 dark:border-slate-700 shadow-lg py-1
               {{ $align === 'left' ? 'left-0' : 'right-0' }}"
    >
        @foreach($supported as $code => $label)
            <a
                href="{{ route('locale.switch', $code) }}"
                class="flex items-center justify-between gap-3 px-4 py-2 text-sm transition-colors
                       {{ $code === $current
                           ? 'text-violet-700 dark:text-violet-300 font-medium bg-violet-50 dark:bg-violet-900/20'
                           : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }}"
            >
                <span>{{ $label }}</span>
                @if($code === $current)
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                @endif
            </a>
        @endforeach
    </div>
</details>
