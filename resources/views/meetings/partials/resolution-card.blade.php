{{-- Single Resolution Card with Voting --}}
<div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-4">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ $resolution->resolution_number }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                    @if($resolution->status === \App\Support\Enums\ResolutionStatus::Proposed) bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300
                    @elseif($resolution->status === \App\Support\Enums\ResolutionStatus::Passed) bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300
                    @elseif($resolution->status === \App\Support\Enums\ResolutionStatus::Failed) bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300
                    @elseif($resolution->status === \App\Support\Enums\ResolutionStatus::Tabled) bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300
                    @elseif($resolution->status === \App\Support\Enums\ResolutionStatus::Withdrawn) bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300
                    @endif">
                    {{ $resolution->status->label() }}
                </span>
            </div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $resolution->title }}</h4>
            @if($resolution->description)
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $resolution->description }}</p>
            @endif
            <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-gray-500 dark:text-gray-400">
                @if($resolution->mover)
                    <span>Mover: {{ $resolution->mover->name }}</span>
                @endif
                @if($resolution->seconder)
                    <span>Seconder: {{ $resolution->seconder->name }}</span>
                @endif
            </div>
        </div>

        @if($isEditable)
            <div class="flex items-center gap-1 flex-shrink-0">
                {{-- Withdraw button --}}
                @if($resolution->status === \App\Support\Enums\ResolutionStatus::Proposed)
                    <form method="POST" action="{{ route('meetings.resolutions.update', [$meeting, $resolution]) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="withdrawn">
                        <button type="submit" class="text-gray-400 hover:text-yellow-500 p-1" title="Withdraw">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        </button>
                    </form>
                @endif

                {{-- Delete button --}}
                <form method="POST" action="{{ route('meetings.resolutions.destroy', [$meeting, $resolution]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-gray-400 hover:text-red-500 p-1" title="Delete"
                        onclick="return confirm('Are you sure you want to delete this resolution?')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </div>
        @endif
    </div>

    {{-- Vote Tally --}}
    @php
        $forVotes = $resolution->votes->where('vote', \App\Support\Enums\VoteChoice::For)->count();
        $againstVotes = $resolution->votes->where('vote', \App\Support\Enums\VoteChoice::Against)->count();
        $abstainVotes = $resolution->votes->where('vote', \App\Support\Enums\VoteChoice::Abstain)->count();
        $totalVotes = $resolution->votes->count();
    @endphp

    <div class="mt-3 pt-3 border-t border-gray-100 dark:border-slate-700">
        <div class="flex items-center gap-4 text-xs">
            <span class="text-green-600 dark:text-green-400 font-medium">For: {{ $forVotes }}</span>
            <span class="text-red-600 dark:text-red-400 font-medium">Against: {{ $againstVotes }}</span>
            <span class="text-gray-500 dark:text-gray-400 font-medium">Abstain: {{ $abstainVotes }}</span>
            <span class="text-gray-400 dark:text-gray-500 ml-auto">{{ $totalVotes }} vote(s)</span>
        </div>

        {{-- Voting Buttons (only for proposed resolutions in editable meetings) --}}
        @if($isEditable && $resolution->status === \App\Support\Enums\ResolutionStatus::Proposed && isset($boardSetting) && $boardSetting->voting_enabled)
            <div x-data="{ showVoteForm: false, selectedAttendee: '' }" class="mt-3">
                <button @click="showVoteForm = !showVoteForm" type="button"
                    class="text-xs text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 font-medium">
                    <span x-text="showVoteForm ? 'Hide Voting' : 'Cast Vote'"></span>
                </button>

                <div x-show="showVoteForm" x-cloak class="mt-2 space-y-2">
                    <select x-model="selectedAttendee"
                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-1.5 text-xs focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        <option value="">Select Attendee</option>
                        @foreach($meeting->attendees->where('is_present', true) as $attendee)
                            <option value="{{ $attendee->id }}">{{ $attendee->name }}</option>
                        @endforeach
                    </select>

                    <div class="flex gap-2">
                        @foreach(\App\Support\Enums\VoteChoice::cases() as $choice)
                            <form method="POST" action="{{ route('meetings.resolutions.vote', $resolution) }}" class="inline">
                                @csrf
                                <input type="hidden" name="vote" value="{{ $choice->value }}">
                                <input type="hidden" name="attendee_id" :value="selectedAttendee">
                                <button type="submit" :disabled="!selectedAttendee"
                                    class="px-3 py-1 text-xs font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed
                                        @if($choice === \App\Support\Enums\VoteChoice::For) bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 hover:bg-green-200
                                        @elseif($choice === \App\Support\Enums\VoteChoice::Against) bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 hover:bg-red-200
                                        @else bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200
                                        @endif">
                                    {{ $choice->label() }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
