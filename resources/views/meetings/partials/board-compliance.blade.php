{{-- Board Compliance Panel: Quorum Badge + Resolutions --}}
@php
    $quorumService = app(\App\Domain\Meeting\Services\QuorumService::class);
    $quorumStatus = $quorumService->check($meeting);
    $boardSetting = \App\Domain\Meeting\Models\BoardSetting::where('organization_id', $meeting->organization_id)->first();
@endphp

<div class="space-y-4">
    {{-- Quorum Status --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Board Compliance</h2>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium
                {{ $quorumStatus['is_met']
                    ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
                    : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300' }}">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 8 8">
                    <circle cx="4" cy="4" r="3" />
                </svg>
                {{ $quorumStatus['is_met'] ? 'Quorum Met' : 'Quorum Not Met' }}
            </span>
        </div>

        <div class="grid grid-cols-3 gap-4 text-center">
            <div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $quorumStatus['present'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Present</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $quorumStatus['required'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Required</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $meeting->attendees->count() }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
            </div>
        </div>

        @if($quorumStatus['type'] !== 'none')
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-3 text-center">
                Quorum type: {{ ucfirst($quorumStatus['type']) }}
                @if($boardSetting)
                    ({{ $boardSetting->quorum_value }}{{ $quorumStatus['type'] === 'percentage' ? '%' : ' members' }})
                @endif
            </p>
        @endif
    </div>

    {{-- Resolutions --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Resolutions</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $meeting->resolutions->count() }} total</span>
        </div>

        @if($meeting->resolutions->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">No resolutions have been created for this meeting.</p>
        @else
            <div class="space-y-3">
                @foreach($meeting->resolutions as $resolution)
                    @include('meetings.partials.resolution-card', ['resolution' => $resolution, 'meeting' => $meeting, 'isEditable' => $isEditable, 'boardSetting' => $boardSetting])
                @endforeach
            </div>
        @endif

        {{-- Add Resolution Form --}}
        @if($isEditable)
            <div x-data="{ showForm: false }" class="mt-4">
                <button @click="showForm = !showForm" type="button"
                    class="inline-flex items-center gap-1.5 text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span x-text="showForm ? 'Cancel' : 'Add Resolution'"></span>
                </button>

                <form x-show="showForm" x-cloak method="POST" action="{{ route('meetings.resolutions.store', $meeting) }}" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <label for="resolution_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" id="resolution_title" required
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none"
                            placeholder="Resolution title">
                    </div>

                    <div>
                        <label for="resolution_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea name="description" id="resolution_description" rows="2"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none resize-none"
                            placeholder="Optional description"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="mover_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mover</label>
                            <select name="mover_id" id="mover_id"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                                <option value="">None</option>
                                @foreach($meeting->attendees as $attendee)
                                    <option value="{{ $attendee->id }}">{{ $attendee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="seconder_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Seconder</label>
                            <select name="seconder_id" id="seconder_id"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                                <option value="">None</option>
                                @foreach($meeting->attendees as $attendee)
                                    <option value="{{ $attendee->id }}">{{ $attendee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                            Add Resolution
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
