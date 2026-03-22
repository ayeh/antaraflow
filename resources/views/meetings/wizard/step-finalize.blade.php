{{-- Step 5: Finalize (MOM Preview + AI Assistant + Status Actions) --}}
<div x-data="{ showFinalizeModal: false, shareModalOpen: false }" class="space-y-6">

    {{-- Warning Banner (if no content) --}}
    @if($meeting->extractions->isEmpty() && $meeting->manualNotes->isEmpty())
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <div>
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">No content generated yet</p>
                    <p class="text-sm text-amber-700 dark:text-amber-300 mt-0.5">
                        Add inputs and run AI extraction to generate MOM content.
                        <button type="button" @click="$root.querySelector('[x-data]').__x.$data.activeStep = 3" class="underline font-medium hover:no-underline">Go to Inputs</button>
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Board Compliance (only for Board Meetings) --}}
    @if($meeting->meeting_type === \App\Support\Enums\MeetingType::BoardMeeting)
        @include('meetings.partials.board-compliance')
    @endif

    {{-- Two-Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Panel: MOM Preview (2/3 width) --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Summary --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Summary</h2>
                @php
                    $summary = $meeting->extractions->firstWhere('type', 'summary');
                @endphp
                @if($summary)
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! nl2br(e($summary->content ?? ($summary->structured_data['content'] ?? 'No summary content.'))) !!}
                    </div>
                @elseif($meeting->summary)
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! nl2br(e($meeting->summary)) !!}
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">No summary available. Run AI extraction from the Inputs step to generate a summary.</p>
                @endif
            </div>

            {{-- Action Items --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Action Items</h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $actionItemStats['completed'] }}/{{ $actionItemStats['total'] }} completed
                    </span>
                </div>

                @if($meeting->actionItems->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">No action items created yet.</p>
                @else
                    <ol class="space-y-2">
                        @foreach($meeting->actionItems as $index => $actionItem)
                            <li class="flex items-start gap-3 p-2 rounded-lg {{ $actionItem->status === \App\Support\Enums\ActionItemStatus::Completed ? 'bg-green-50 dark:bg-green-900/10' : '' }}">
                                <span class="flex-shrink-0 h-6 w-6 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-xs font-medium text-gray-600 dark:text-gray-400">{{ $index + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-gray-900 dark:text-white {{ $actionItem->status === \App\Support\Enums\ActionItemStatus::Completed ? 'line-through text-gray-400 dark:text-gray-500' : '' }}">
                                        {{ $actionItem->title }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2 mt-1">
                                        @if($actionItem->assignedTo)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $actionItem->assignedTo->name }}</span>
                                        @endif
                                        @if($actionItem->due_date)
                                            <span class="text-xs {{ $actionItem->due_date->isPast() && !in_array($actionItem->status, [\App\Support\Enums\ActionItemStatus::Completed, \App\Support\Enums\ActionItemStatus::Cancelled]) ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                                Due {{ $actionItem->due_date->format('d M Y') }}
                                            </span>
                                        @endif
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium
                                            @if($actionItem->priority === \App\Support\Enums\ActionItemPriority::Critical) bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300
                                            @elseif($actionItem->priority === \App\Support\Enums\ActionItemPriority::High) bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300
                                            @elseif($actionItem->priority === \App\Support\Enums\ActionItemPriority::Medium) bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300
                                            @else bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300
                                            @endif">
                                            {{ ucfirst($actionItem->priority?->value ?? 'medium') }}
                                        </span>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            {{-- Decisions with Follow-Up Status --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Decisions</h2>
                @php
                    $decisions = $meeting->extractions->firstWhere('type', 'decisions');
                    $decisionStatuses = isset($decisionTracker) ? $decisionTracker : [];
                @endphp
                @if(!empty($decisionStatuses))
                    <ol class="space-y-3">
                        @foreach($decisionStatuses as $index => $ds)
                            <li class="flex items-start gap-3 p-3 rounded-lg {{ match($ds['status']) { 'followed_up' => 'bg-green-50 dark:bg-green-900/10', 'stale' => 'bg-red-50 dark:bg-red-900/10', default => 'bg-gray-50 dark:bg-slate-700/30' } }}">
                                <span class="flex-shrink-0 h-6 w-6 rounded-full flex items-center justify-center text-xs font-medium
                                    {{ match($ds['status']) { 'followed_up' => 'bg-green-100 text-green-700', 'stale' => 'bg-red-100 text-red-700', default => 'bg-gray-100 text-gray-600' } }}">{{ $index + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ $ds['decision'] }}</p>
                                    @if(!empty($ds['made_by']) && $ds['made_by'] !== 'Unspecified speaker')
                                        <span class="text-xs text-gray-500 dark:text-gray-400">— {{ $ds['made_by'] }}</span>
                                    @endif
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium
                                            {{ match($ds['status']) { 'followed_up' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300', 'stale' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300', default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' } }}">
                                            {{ match($ds['status']) { 'followed_up' => 'Followed Up', 'stale' => 'Stale (' . $ds['days_since'] . 'd)', default => 'Pending' } }}
                                        </span>
                                        @foreach($ds['linked_action_items'] as $linkedItem)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">→ {{ $linkedItem['title'] }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @elseif($decisions && !empty($decisions->structured_data) && !isset($decisions->structured_data['custom_template']))
                    <ol class="space-y-3 list-decimal list-inside">
                        @foreach($decisions->structured_data as $decision)
                            <li class="text-sm text-gray-900 dark:text-white">
                                {{ is_array($decision) ? ($decision['decision'] ?? $decision['content'] ?? $decision['text'] ?? '') : $decision }}
                                @if(is_array($decision) && !empty($decision['made_by']) && $decision['made_by'] !== 'Unspecified speaker')
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">— {{ $decision['made_by'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @elseif($decisions && $decisions->content)
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! nl2br(e($decisions->content)) !!}
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">No decisions recorded.</p>
                @endif
            </div>

            {{-- Risks --}}
            @php
                $risks = $meeting->extractions->firstWhere('type', 'risks');
            @endphp
            @if($risks && !empty($risks->structured_data) && !isset($risks->structured_data['custom_template']))
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Risks & Concerns</h2>
                    <div class="space-y-3">
                        @foreach($risks->structured_data as $risk)
                            <div class="flex items-start gap-3 p-3 rounded-lg {{ match($risk['severity'] ?? 'medium') { 'high' => 'bg-red-50 dark:bg-red-900/10', 'medium' => 'bg-amber-50 dark:bg-amber-900/10', 'low' => 'bg-yellow-50 dark:bg-yellow-900/10', default => 'bg-gray-50 dark:bg-slate-700/30' } }}">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ match($risk['severity'] ?? 'medium') { 'high' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300', 'medium' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300', 'low' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300', default => 'bg-gray-100 text-gray-800' } }}">
                                    {{ ucfirst($risk['severity'] ?? 'medium') }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ $risk['risk'] ?? '' }}</p>
                                    @if(!empty($risk['mitigation']))
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><span class="font-medium">Mitigation:</span> {{ $risk['mitigation'] }}</p>
                                    @endif
                                    @if(!empty($risk['raised_by']))
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Raised by {{ $risk['raised_by'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Issues --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Issues</h2>
                @php
                    $issues = $meeting->extractions->firstWhere('type', 'issues');
                @endphp
                @if($issues && !empty($issues->structured_data))
                    <ol class="space-y-3 list-decimal list-inside">
                        @foreach($issues->structured_data as $issue)
                            <li class="text-sm text-gray-900 dark:text-white">
                                {{ is_array($issue) ? ($issue['issue'] ?? $issue['content'] ?? $issue['text'] ?? $issue['description'] ?? '') : $issue }}
                            </li>
                        @endforeach
                    </ol>
                @elseif($issues && $issues->content)
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! nl2br(e($issues->content)) !!}
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">No issues recorded.</p>
                @endif
            </div>
        </div>

        {{-- Right Panel: Sidebars (1/3 width) --}}
        <div class="lg:col-span-1 space-y-4">
            {{-- Meeting Info --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Meeting Info</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">MOM Number</span>
                        <span class="text-sm font-mono font-medium text-gray-900 dark:text-white">{{ $meeting->mom_number ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Date</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $meeting->meeting_date->format('d M Y') }}</span>
                    </div>
                    @if($meeting->project)
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Project</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $meeting->project->name }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            @if($meeting->status === \App\Support\Enums\MeetingStatus::Draft) bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300
                            @elseif($meeting->status === \App\Support\Enums\MeetingStatus::InProgress) bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300
                            @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Finalized) bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300
                            @elseif($meeting->status === \App\Support\Enums\MeetingStatus::Approved) bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300
                            @endif">
                            {{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Attendees</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $attendeeStats['total'] }} ({{ $attendeeStats['present'] }} present)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Action Items</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $actionItemStats['completed'] }}/{{ $actionItemStats['total'] }}</span>
                    </div>
                    @if($meeting->prepared_by)
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Prepared By</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $meeting->prepared_by }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Related Meetings --}}
            @include('meetings.partials.related-meetings', ['relatedMeetings' => $relatedMeetings ?? collect()])

            {{-- AI Assistant --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">AI Assistant</h3>

                <div class="space-y-3">
                    <form method="POST" action="{{ route('meetings.extract', $meeting) }}">
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-gradient-to-r from-violet-600 to-violet-700 hover:from-violet-700 hover:to-violet-800 transition-all">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                            Generate Summary
                        </button>
                    </form>

                    {{-- AI Chat Link --}}
                    <a href="{{ route('meetings.chat.index', $meeting) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                        Ask AI About This Meeting
                    </a>

                    {{-- Usage Stats --}}
                    @if($meeting->aiConversations->isNotEmpty())
                        <div class="pt-3 border-t border-gray-200 dark:border-slate-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $meeting->aiConversations->count() }} AI conversation(s)
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Export Options --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Export</h3>
                <div class="space-y-2">
                    <a href="{{ route('meetings.export.pdf', $meeting) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        <svg class="h-4 w-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Download PDF
                    </a>
                    <a href="{{ route('meetings.export.word', $meeting) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        <svg class="h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Download Word
                    </a>
                    <a href="{{ route('meetings.export.csv', $meeting) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Download CSV (Action Items)
                    </a>
                    <a href="{{ route('meetings.export.json', $meeting) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                        <svg class="h-4 w-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        Download JSON
                    </a>

                    {{-- Email MOM --}}
                    @include('meetings.partials.email-distribution-modal')
                </div>

                {{-- Export History --}}
                @include('meetings.partials.export-history')
            </div>

            {{-- Guest Access / Share Section --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-300">Guest Access Links</h3>
                    <button @click="shareModalOpen = true"
                            class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition-colors">
                        + Create Link
                    </button>
                </div>

                @if($meeting->guestAccesses->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($meeting->guestAccesses as $access)
                            <div class="flex items-center justify-between text-sm py-1.5 border-b border-gray-100 dark:border-slate-700 last:border-0">
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-slate-300">{{ $access->label ?? 'Guest Link' }}</span>
                                    <button onclick="navigator.clipboard.writeText('{{ route('guest.mom', $access->token) }}')"
                                            class="ml-2 text-xs text-violet-600 hover:text-violet-700 dark:text-violet-400">
                                        Copy link
                                    </button>
                                </div>
                                <form action="{{ route('meetings.guest-access.destroy', $access) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">Revoke</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-400 dark:text-slate-500">No guest links created yet.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Create Link Modal --}}
    <div x-show="shareModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         @click.self="shareModalOpen = false">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">Create Guest Access Link</h3>
            <form action="{{ route('meetings.guest-access.store', $meeting) }}" method="POST">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Label (optional)</label>
                        <input type="text" name="label" placeholder="e.g. Client ABC"
                               class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Expires (optional)</label>
                        <input type="date" name="expires_at"
                               class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" @click="shareModalOpen = false"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-slate-400">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg">
                        Create Link
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Finalize Bar --}}
    @if($isEditable)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-amber-200 dark:border-amber-800 p-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Ready to finalize?</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Review the content above. Once finalized, the MOM cannot be edited.</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="showFinalizeModal = true"
                    class="inline-flex items-center gap-2 bg-amber-500 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-amber-600 transition-colors flex-shrink-0"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Finalize MOM
                </button>
            </div>
        </div>

        {{-- Finalize Confirmation Modal --}}
        <div x-show="showFinalizeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div x-show="showFinalizeModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/50" @click="showFinalizeModal = false"></div>

                <div x-show="showFinalizeModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="text-center">
                        <div class="mx-auto h-12 w-12 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mb-4">
                            <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Finalize Meeting</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                            Are you sure you want to finalize this MOM? Once finalized, the content cannot be edited. A version snapshot will be created.
                        </p>
                        <div class="flex gap-3 justify-center">
                            <button
                                type="button"
                                @click="showFinalizeModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                            >
                                Cancel
                            </button>
                            <form method="POST" action="{{ route('meetings.finalize', $meeting) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-amber-500 rounded-lg hover:bg-amber-600 transition-colors">
                                    Yes, Finalize
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Post-Finalize Actions --}}
    @if($meeting->status === \App\Support\Enums\MeetingStatus::Finalized)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-green-200 dark:border-green-800 p-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Meeting Finalized</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">This MOM is locked. Approve it or revert to draft for changes.</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('meetings.approve', $meeting) }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Approve
                        </button>
                    </form>
                    <form method="POST" action="{{ route('meetings.revert', $meeting) }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-gray-300 px-5 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            Revert to Draft
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Approved State --}}
    @if($meeting->status === \App\Support\Enums\MeetingStatus::Approved)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-green-200 dark:border-green-800 p-4">
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                </svg>
                <div>
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">Meeting Approved</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">This MOM has been approved. Use the export options above to download.</p>
                </div>
            </div>
        </div>
    @endif
</div>
