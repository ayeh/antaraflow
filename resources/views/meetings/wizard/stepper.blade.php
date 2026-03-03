{{-- Wizard Stepper: 5-step horizontal progress bar --}}
{{-- Expects Alpine.js `activeStep` variable from parent x-data --}}
@php
    $steps = [
        1 => 'Setup',
        2 => 'Attendees',
        3 => 'Inputs',
        4 => 'Review',
        5 => 'Finalize',
    ];
@endphp

<nav aria-label="Meeting wizard progress" class="mb-6">
    <ol class="flex items-center w-full">
        @foreach ($steps as $number => $label)
            {{-- Step item --}}
            <li class="flex items-center {{ $number < count($steps) ? 'flex-1' : '' }}">
                {{-- Step circle + label --}}
                <button
                    type="button"
                    @click="activeStep = {{ $number }}"
                    class="flex flex-col items-center gap-1.5 group relative"
                    :aria-current="activeStep === {{ $number }} ? 'step' : false"
                >
                    {{-- Circle --}}
                    <span
                        class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-semibold shrink-0 transition-colors"
                        :class="{
                            {{-- Step 1 always completed (green checkmark) unless it's the active step --}}
                            'bg-green-500 text-white': {{ $number }} === 1 && activeStep !== 1,
                            {{-- Completed steps before current (green checkmark) --}}
                            'bg-green-500 text-white': {{ $number }} !== 1 && {{ $number }} < activeStep,
                            {{-- Current step (violet) --}}
                            'bg-violet-600 text-white ring-2 ring-violet-300 dark:ring-violet-500/50': activeStep === {{ $number }},
                            {{-- Future steps (gray) --}}
                            'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 group-hover:bg-gray-300 dark:group-hover:bg-gray-600': {{ $number }} > activeStep && !({{ $number }} === 1),
                        }"
                    >
                        {{-- Checkmark for completed steps --}}
                        <template x-if="({{ $number }} === 1 && activeStep !== 1) || ({{ $number }} !== 1 && {{ $number }} < activeStep)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </template>
                        {{-- Number for current and future steps --}}
                        <template x-if="!({{ $number }} === 1 && activeStep !== 1) && !({{ $number }} !== 1 && {{ $number }} < activeStep)">
                            <span>{{ $number }}</span>
                        </template>
                    </span>

                    {{-- Label --}}
                    <span
                        class="text-xs font-medium transition-colors whitespace-nowrap"
                        :class="{
                            'text-green-600 dark:text-green-400': ({{ $number }} === 1 && activeStep !== 1) || ({{ $number }} !== 1 && {{ $number }} < activeStep),
                            'text-violet-600 dark:text-violet-400 font-semibold': activeStep === {{ $number }},
                            'text-gray-500 dark:text-gray-400': {{ $number }} > activeStep && !({{ $number }} === 1),
                        }"
                    >
                        {{ $label }}
                    </span>
                </button>

                {{-- Connecting line (not after last step) --}}
                @if ($number < count($steps))
                    <div
                        class="flex-1 h-0.5 mx-2 transition-colors"
                        :class="{
                            'bg-green-500': {{ $number }} < activeStep || ({{ $number }} === 1 && activeStep > 1),
                            'bg-gray-200 dark:bg-gray-700': {{ $number }} >= activeStep && !({{ $number }} === 1 && activeStep > 1),
                        }"
                    ></div>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
