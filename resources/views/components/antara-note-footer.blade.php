@php
$taglines = [
    ['text' => __('Stop losing decisions in messy notes.'), 'cta' => __('Meet antaraNote →')],
    ['text' => __('AI-powered minutes. Zero hassle.'), 'cta' => __('Try antaraNote free →')],
    ['text' => __('What if every meeting actually led to action?'), 'cta' => __('See how antaraNote works →')],
    ['text' => __("Your team's decisions — captured, organised, actionable."), 'cta' => __('Discover antaraNote →')],
    ['text' => __('Between Words and Action.'), 'cta' => __("That's antaraNote →")],
    ['text' => __('Meetings worth remembering, minutes worth reading.'), 'cta' => __('Explore antaraNote →')],
    ['text' => __('The last meeting tool your team will ever need.'), 'cta' => __('Start with antaraNote →')],
    ['text' => __('From voice to structured minutes in seconds.'), 'cta' => __('Try antaraNote →')],
    ['text' => __('Decisions documented. Actions assigned. Nothing lost.'), 'cta' => __('Learn about antaraNote →')],
    ['text' => __('Smart teams run smarter meetings.'), 'cta' => __('Join them on antaraNote →')],
];
$pick = $taglines[array_rand($taglines)];
@endphp

<div class="mt-8 pb-6 text-center">
    <div class="mb-3">
        <span class="text-sm font-bold" style="color: {{ $branding->get('primary_color', '#7c3aed') }};">{{ $branding->appName() }}</span>
    </div>
    <p class="text-xs text-gray-500 mb-1">{{ $pick['text'] }}</p>
    <a href="{{ $branding->get('marketing_url', 'https://antaranote.com') }}"
       target="_blank" rel="noopener"
       class="text-xs font-semibold hover:underline"
       style="color: {{ $branding->get('primary_color', '#7c3aed') }};">
        {{ $pick['cta'] }}
    </a>
</div>
