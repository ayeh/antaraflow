<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900">AI Insights</h3>
        <form method="POST" action="{{ route('meetings.extract', $meeting) }}" class="inline">
            @csrf
            <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Run AI Extraction</button>
        </form>
    </div>

    @php
        $extractions = $meeting->extractions()->latest()->get();
    @endphp

    @if($extractions->isNotEmpty())
        @foreach($extractions as $extraction)
            <div class="border border-gray-200 rounded-lg p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">{{ $extraction->created_at->format('M j, Y g:i A') }}</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">{{ ucfirst($extraction->type ?? 'extraction') }}</span>
                </div>

                @if($extraction->summary)
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-1">Summary</h4>
                        <p class="text-sm text-gray-600">{{ $extraction->summary }}</p>
                    </div>
                @endif

                @if($extraction->extracted_data)
                    @if(isset($extraction->extracted_data['action_items']))
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-1">Action Items</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                                @foreach($extraction->extracted_data['action_items'] as $item)
                                    <li>{{ is_string($item) ? $item : ($item['title'] ?? $item['description'] ?? '') }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(isset($extraction->extracted_data['decisions']))
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-1">Decisions</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                                @foreach($extraction->extracted_data['decisions'] as $decision)
                                    <li>{{ is_string($decision) ? $decision : ($decision['description'] ?? '') }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
    @else
        <p class="text-sm text-gray-500 text-center py-8">No AI extractions yet. Click "Run AI Extraction" to analyze the meeting content.</p>
    @endif

    @php
        $topics = $meeting->topics()->get();
    @endphp
    @if($topics->isNotEmpty())
        <div>
            <h4 class="text-sm font-medium text-gray-700 mb-2">Topics</h4>
            <div class="flex flex-wrap gap-2">
                @foreach($topics as $topic)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ $topic->title }}</span>
                @endforeach
            </div>
        </div>
    @endif
</div>
