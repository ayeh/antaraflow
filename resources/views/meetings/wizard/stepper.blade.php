{{-- Redesigned Meeting Stepper --}}
@php
    $steps = ['Setup', 'Attendees', 'Inputs', 'Review', 'Finalize'];
@endphp
<div class="flex items-center mb-6">
    @foreach($steps as $index => $label)
        @php $stepNumber = $index + 1; @endphp

        {{-- Step circle + label --}}
        <div class="flex flex-col items-center">
            <div
                class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-200"
                :class="
                    activeStep > {{ $stepNumber }}
                        ? 'bg-violet-600 text-white'
                        : activeStep === {{ $stepNumber }}
                            ? 'bg-violet-600 text-white ring-4 ring-violet-100 dark:ring-violet-900/30'
                            : 'bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-gray-400'
                "
            >
                <template x-if="activeStep > {{ $stepNumber }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </template>
                <template x-if="activeStep <= {{ $stepNumber }}">
                    <span>{{ $stepNumber }}</span>
                </template>
            </div>
            <span
                class="mt-1.5 text-xs font-medium transition-colors duration-200 whitespace-nowrap hidden sm:block"
                :class="activeStep >= {{ $stepNumber }} ? 'text-violet-600 dark:text-violet-400' : 'text-gray-400 dark:text-slate-500'"
            >{{ $label }}</span>
        </div>

        {{-- Connector line (not after last step) --}}
        @if($stepNumber < count($steps))
        <div class="flex-1 h-0.5 mx-2 mb-5 transition-colors duration-200"
             :class="activeStep > {{ $stepNumber }} ? 'bg-violet-600' : 'bg-gray-200 dark:bg-slate-700'">
        </div>
        @endif
    @endforeach
</div>
