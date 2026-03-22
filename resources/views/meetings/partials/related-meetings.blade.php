{{-- Related Meetings via Knowledge Graph --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Related Meetings</h3>

    @if($relatedMeetings->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400 italic">No related meetings found.</p>
    @else
        <div class="space-y-3">
            @foreach($relatedMeetings as $related)
                <a href="{{ route('meetings.show', $related->meeting) }}" class="block p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors border border-gray-100 dark:border-slate-700">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $related->meeting->title }}</p>
                    <div class="flex items-center gap-2 mt-1.5">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $related->meeting->meeting_date?->format('d M Y') }}
                        </span>
                        @foreach($related->link_types as $linkType)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium
                                @if($linkType === \App\Support\Enums\KnowledgeLinkType::RelatedTo) bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300
                                @elseif($linkType === \App\Support\Enums\KnowledgeLinkType::FollowsUp) bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300
                                @elseif($linkType === \App\Support\Enums\KnowledgeLinkType::Contradicts) bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300
                                @elseif($linkType === \App\Support\Enums\KnowledgeLinkType::Supersedes) bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300
                                @endif">
                                {{ $linkType->label() }}
                            </span>
                        @endforeach
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
