<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900">Action Items</h3>
        <a href="{{ route('meetings.action-items.create', $meeting) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">Add Action Item</a>
    </div>

    @php
        $actionItems = $meeting->actionItems()->with(['assignedTo', 'createdBy'])->get();
    @endphp

    @if($actionItems->isNotEmpty())
        <div class="divide-y divide-gray-200">
            @foreach($actionItems as $item)
                <div class="py-4 flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('meetings.action-items.show', [$meeting, $item]) }}" class="text-sm font-medium text-gray-900 hover:text-blue-600">{{ $item->title }}</a>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                @if($item->priority->value === 'critical') bg-red-100 text-red-700
                                @elseif($item->priority->value === 'high') bg-orange-100 text-orange-700
                                @elseif($item->priority->value === 'medium') bg-yellow-100 text-yellow-700
                                @else bg-gray-100 text-gray-700
                                @endif">
                                {{ ucfirst($item->priority->value) }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                @if($item->status->value === 'completed') bg-green-100 text-green-700
                                @elseif($item->status->value === 'in_progress') bg-blue-100 text-blue-700
                                @elseif($item->status->value === 'cancelled') bg-gray-100 text-gray-500
                                @else bg-gray-100 text-gray-700
                                @endif">
                                {{ ucfirst(str_replace('_', ' ', $item->status->value)) }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            @if($item->assignedTo)
                                Assigned to {{ $item->assignedTo->name }}
                            @endif
                            @if($item->due_date)
                                &middot; Due {{ $item->due_date->format('M j, Y') }}
                            @endif
                        </div>
                    </div>
                    @if(!in_array($item->status->value, ['completed', 'cancelled', 'carried_forward']))
                        <form method="POST" action="{{ route('meetings.action-items.carry-forward', [$meeting, $item]) }}" class="ml-4" x-data="{ showForm: false }">
                            @csrf
                            <button type="button" @click="showForm = !showForm" class="text-xs text-gray-500 hover:text-gray-700">Carry Forward</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 text-center py-8">No action items yet.</p>
    @endif
</div>
