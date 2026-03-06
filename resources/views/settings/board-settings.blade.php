@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Board Meeting Settings</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure quorum requirements and voting rules for board meetings</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('settings.board.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Quorum Settings --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quorum Settings</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="quorum_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quorum Type <span class="text-red-500">*</span></label>
                    <select name="quorum_type" id="quorum_type"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                        <option value="percentage" {{ old('quorum_type', $boardSetting->quorum_type) === 'percentage' ? 'selected' : '' }}>Percentage of Attendees</option>
                        <option value="count" {{ old('quorum_type', $boardSetting->quorum_type) === 'count' ? 'selected' : '' }}>Fixed Count</option>
                    </select>
                    @error('quorum_type')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="quorum_value" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quorum Value <span class="text-red-500">*</span></label>
                    <input type="number" name="quorum_value" id="quorum_value" min="1" max="100"
                        value="{{ old('quorum_value', $boardSetting->quorum_value) }}"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">For percentage type, this is the % of attendees required. For count type, this is the exact number needed.</p>
                    @error('quorum_value')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Role Requirements --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Role Requirements</h2>

            <div class="space-y-4">
                <label class="flex items-center gap-3">
                    <input type="hidden" name="require_chair" value="0">
                    <input type="checkbox" name="require_chair" value="1"
                        {{ old('require_chair', $boardSetting->require_chair) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500">
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Require Chair</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">A chairperson must be assigned to the meeting</p>
                    </div>
                </label>

                <label class="flex items-center gap-3">
                    <input type="hidden" name="require_secretary" value="0">
                    <input type="checkbox" name="require_secretary" value="1"
                        {{ old('require_secretary', $boardSetting->require_secretary) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500">
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Require Secretary</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">A secretary must be assigned to take minutes</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Voting Settings --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Voting Settings</h2>

            <div class="space-y-4">
                <label class="flex items-center gap-3">
                    <input type="hidden" name="voting_enabled" value="0">
                    <input type="checkbox" name="voting_enabled" value="1"
                        {{ old('voting_enabled', $boardSetting->voting_enabled) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500">
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Enable Voting</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Allow voting on resolutions during board meetings</p>
                    </div>
                </label>

                <label class="flex items-center gap-3">
                    <input type="hidden" name="chair_casting_vote" value="0">
                    <input type="checkbox" name="chair_casting_vote" value="1"
                        {{ old('chair_casting_vote', $boardSetting->chair_casting_vote) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500">
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Chair Casting Vote</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">In case of a tie, the chairperson's vote breaks the deadlock (resolution passes)</p>
                    </div>
                </label>

                <label class="flex items-center gap-3">
                    <input type="hidden" name="block_finalization_without_quorum" value="0">
                    <input type="checkbox" name="block_finalization_without_quorum" value="1"
                        {{ old('block_finalization_without_quorum', $boardSetting->block_finalization_without_quorum) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500">
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Block Finalization Without Quorum</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Prevent board meetings from being finalized if quorum is not met</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="bg-violet-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                Save Board Settings
            </button>
        </div>
    </form>
</div>
@endsection
