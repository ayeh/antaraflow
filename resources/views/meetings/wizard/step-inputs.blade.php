{{-- Step 3: Inputs (Transcriptions, Notes, Documents) --}}
<div class="space-y-6">
    {{-- Transcriptions --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Transcriptions</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $meeting->transcriptions->count() }} file(s)</span>
        </div>

        @if($meeting->transcriptions->isEmpty())
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                </svg>
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No transcriptions</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload audio files or add transcriptions to this meeting.</p>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Transcription management content coming soon.</p>
        @endif
    </div>

    {{-- Manual Notes --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Manual Notes</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $meeting->manualNotes->count() }} note(s)</span>
        </div>

        @if($meeting->manualNotes->isEmpty())
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No manual notes</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add notes to capture key points from this meeting.</p>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Notes management content coming soon.</p>
        @endif
    </div>

    {{-- Other Inputs --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Other Inputs</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $meeting->inputs->count() }} input(s)</span>
        </div>

        @if($meeting->inputs->isEmpty())
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No additional inputs</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload documents or add other inputs to this meeting.</p>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Input management content coming soon.</p>
        @endif
    </div>
</div>
