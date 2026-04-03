@php
$taglines = [
    ['text' => 'Stop losing decisions in messy notes.', 'cta' => 'Meet antaraNote →'],
    ['text' => 'AI-powered minutes. Zero hassle.', 'cta' => 'Try antaraNote free →'],
    ['text' => 'What if every meeting actually led to action?', 'cta' => 'See how antaraNote works →'],
    ['text' => 'Your team\'s decisions — captured, organised, actionable.', 'cta' => 'Discover antaraNote →'],
    ['text' => 'Between Words and Action.', 'cta' => 'That\'s antaraNote →'],
    ['text' => 'Meetings worth remembering, minutes worth reading.', 'cta' => 'Explore antaraNote →'],
    ['text' => 'The last meeting tool your team will ever need.', 'cta' => 'Start with antaraNote →'],
    ['text' => 'From voice to structured minutes in seconds.', 'cta' => 'Try antaraNote →'],
    ['text' => 'Decisions documented. Actions assigned. Nothing lost.', 'cta' => 'Learn about antaraNote →'],
    ['text' => 'Smart teams run smarter meetings.', 'cta' => 'Join them on antaraNote →'],
];
$pick = $taglines[array_rand($taglines)];
@endphp

<div class="mt-8 pb-6 text-center">
    <div class="inline-flex items-center gap-2 mb-3">
        {{-- Waveform mark --}}
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 68 50" width="28" height="20" aria-hidden="true">
            <rect x="0"  y="21" width="7" height="16" rx="3.5" fill="#0D7377"/>
            <rect x="11" y="10" width="7" height="36" rx="3.5" fill="#0D7377"/>
            <rect x="22" y="16" width="7" height="25" rx="3.5" fill="#0D7377"/>
            <rect x="33" y="4"  width="7" height="50" rx="3.5" fill="#0D7377"/>
            <rect x="44" y="13" width="7" height="31" rx="3.5" fill="#0D7377"/>
            <rect x="55" y="8"  width="7" height="43" rx="3.5" fill="#0D7377"/>
            <rect x="66" y="19" width="7" height="22" rx="3.5" fill="#0D7377"/>
        </svg>
        <span class="text-sm font-medium" style="color:#1E293B;">
            <span style="font-weight:400;">antara</span><span style="font-weight:700;">Note</span>
        </span>
    </div>
    <p class="text-xs text-gray-500 mb-1">{{ $pick['text'] }}</p>
    <a href="{{ $branding->get('marketing_url', 'https://antaranote.com') }}"
       target="_blank" rel="noopener"
       class="text-xs font-semibold hover:underline"
       style="color:#0D7377;">
        {{ $pick['cta'] }}
    </a>
</div>
