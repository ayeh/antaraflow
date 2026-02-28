<div class="space-y-6">
    @if($meeting->summary)
        <div>
            <h3 class="text-sm font-medium text-gray-500 mb-2">Summary</h3>
            <p class="text-sm text-gray-900">{{ $meeting->summary }}</p>
        </div>
    @endif

    <div>
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-medium text-gray-500">Content</h3>
            @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft)
                <a href="{{ route('meetings.edit', $meeting) }}" class="text-sm text-blue-600 hover:text-blue-700">Edit</a>
            @endif
        </div>
        @if($meeting->content)
            <div class="prose prose-sm max-w-none text-gray-900">{!! nl2br(e($meeting->content)) !!}</div>
        @else
            <p class="text-sm text-gray-500 italic">No content added yet.</p>
        @endif
    </div>

    @if($meeting->createdBy)
        <div class="pt-4 border-t border-gray-200">
            <p class="text-xs text-gray-500">Created by {{ $meeting->createdBy->name }} on {{ $meeting->created_at->format('M j, Y g:i A') }}</p>
        </div>
    @endif
</div>
