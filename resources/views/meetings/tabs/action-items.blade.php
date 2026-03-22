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
                            <span
                                x-data="{ clientVisible: {{ $item->client_visible ? 'true' : 'false' }} }"
                                x-tooltip="clientVisible ? 'Visible to clients' : 'Internal only'"
                            >
                                <button
                                    type="button"
                                    @click="fetch('{{ route('action-items.toggle-visibility', $item) }}', {method:'PATCH', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}).then(r=>r.json()).then(d=>{ clientVisible = d.client_visible })"
                                    :title="clientVisible ? 'Visible to clients' : 'Internal only'"
                                    :class="clientVisible ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-slate-500'"
                                    class="p-1 rounded hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              :d="clientVisible
                                                ? 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'
                                                : 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21'" />
                                    </svg>
                                </button>
                            </span>
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
