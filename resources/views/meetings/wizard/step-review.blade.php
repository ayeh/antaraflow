{{-- Step 4: Review (AI Extractions, Action Items, Comments, Sharing) --}}
<div class="space-y-6">
    {{-- AI Extractions --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">AI Extractions</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $meeting->extractions->count() }} extraction(s)</span>
        </div>

        @if($meeting->extractions->isEmpty())
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No AI extractions</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Run AI extraction after adding transcriptions or notes.</p>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">AI extraction review content coming soon.</p>
        @endif
    </div>

    {{-- Action Items --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Action Items</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $actionItemStats['completed'] }}/{{ $actionItemStats['total'] }} completed
            </span>
        </div>

        @if($meeting->actionItems->isEmpty())
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No action items</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Action items will appear here once created or extracted by AI.</p>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Action items review content coming soon.</p>
        @endif
    </div>

    {{-- Comments --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Comments</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $comments->count() }} comment(s)</span>
        </div>

        @if($comments->isEmpty())
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No comments</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add comments to discuss this meeting with collaborators.</p>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Comments content coming soon.</p>
        @endif
    </div>

    {{-- Sharing --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Sharing</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $shares->count() }} share(s)</span>
        </div>

        @if($shares->isEmpty())
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                </svg>
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">Not shared</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Share this meeting with team members or external collaborators.</p>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Sharing management content coming soon.</p>
        @endif
    </div>
</div>
