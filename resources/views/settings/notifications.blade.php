@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notification Settings</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Choose how and when you want to be notified</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('settings.notifications.update') }}">
        @csrf
        @method('PUT')

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <div class="grid grid-cols-3 gap-4 items-center">
                    <div class="col-span-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Event</div>
                    <div class="text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Email</div>
                    <div class="text-center text-sm font-semibold text-gray-700 dark:text-gray-300">In-App</div>
                </div>
            </div>

            @php
            $eventLabels = [
                'mention_in_comment' => ['label' => 'Mention in Comment', 'description' => 'When someone mentions you in a comment'],
                'action_item_assigned' => ['label' => 'Action Item Assigned', 'description' => 'When an action item is assigned to you'],
                'meeting_finalized' => ['label' => 'Meeting Finalized', 'description' => 'When a meeting you attend is finalized'],
                'action_item_overdue' => ['label' => 'Action Item Overdue', 'description' => 'When your action items become overdue'],
            ];
            @endphp

            <div class="divide-y divide-gray-200 dark:divide-slate-700">
                @foreach($eventLabels as $key => $info)
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-3 gap-4 items-center">
                            <div class="col-span-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $info['label'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $info['description'] }}</p>
                            </div>
                            <div class="flex justify-center">
                                <input type="hidden" name="{{ $key }}[email]" value="0">
                                <input type="checkbox"
                                    name="{{ $key }}[email]"
                                    value="1"
                                    {{ ($prefs[$key]['email'] ?? false) ? 'checked' : '' }}
                                    class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
                            </div>
                            <div class="flex justify-center">
                                <input type="hidden" name="{{ $key }}[in_app]" value="0">
                                <input type="checkbox"
                                    name="{{ $key }}[in_app]"
                                    value="1"
                                    {{ ($prefs[$key]['in_app'] ?? false) ? 'checked' : '' }}
                                    class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end mt-6">
            <button type="submit"
                class="bg-violet-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
                Save Preferences
            </button>
        </div>
    </form>
</div>
@endsection
