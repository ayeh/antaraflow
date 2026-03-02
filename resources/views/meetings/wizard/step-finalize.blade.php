{{-- Step 5: Finalize (Export, Approve, Status Actions) --}}
<div class="space-y-6">
    {{-- Meeting Summary --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Meeting Summary</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Attendees</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $attendeeStats['total'] }} ({{ $attendeeStats['present'] }} present)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Action Items</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $actionItemStats['completed'] }}/{{ $actionItemStats['total'] }} completed</span>
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Transcriptions</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $meeting->transcriptions->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">AI Extractions</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $meeting->extractions->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Comments</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $comments->count() }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Status Actions --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Actions</h2>

        <div class="flex flex-wrap gap-3">
            @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft || $meeting->status === \App\Support\Enums\MeetingStatus::InProgress)
                <form method="POST" action="{{ route('meetings.finalize', $meeting) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 bg-yellow-500 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-yellow-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Finalize Meeting
                    </button>
                </form>
            @endif

            @if($meeting->status === \App\Support\Enums\MeetingStatus::Finalized)
                <form method="POST" action="{{ route('meetings.approve', $meeting) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 bg-green-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Approve Meeting
                    </button>
                </form>
            @endif

            @if($meeting->status !== \App\Support\Enums\MeetingStatus::Draft)
                <form method="POST" action="{{ route('meetings.revert', $meeting) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        Revert to Draft
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Export Options --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Export</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <a href="{{ route('meetings.export.pdf', $meeting) }}" class="inline-flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 px-4 py-3 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Download PDF
            </a>
            <a href="{{ route('meetings.export.word', $meeting) }}" class="inline-flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 px-4 py-3 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Download Word
            </a>
            <a href="{{ route('meetings.export.csv', $meeting) }}" class="inline-flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 px-4 py-3 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Download CSV
            </a>
        </div>
    </div>
</div>
