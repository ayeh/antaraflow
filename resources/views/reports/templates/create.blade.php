@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('reports.index') }}" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Report Template</h1>
    </div>

    <form method="POST" action="{{ route('reports.store') }}" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-6">
        @csrf

        @if($errors->any())
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none"
                placeholder="e.g. Monthly Board Summary">
        </div>

        <div>
            <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Report Type <span class="text-red-500">*</span></label>
            <select name="type" id="type" required
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                @foreach($reportTypes as $type)
                    <option value="{{ $type->value }}" {{ old('type') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="filters_start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="date" name="filters[start_date]" id="filters_start_date" value="{{ old('filters.start_date') }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
            </div>

            <div>
                <label for="filters_end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="date" name="filters[end_date]" id="filters_end_date" value="{{ old('filters.end_date') }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
            </div>
        </div>

        <div>
            <label for="schedule" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Schedule <span class="text-gray-400 font-normal">(optional cron expression)</span></label>
            <input type="text" name="schedule" id="schedule" value="{{ old('schedule') }}"
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none"
                placeholder="e.g. 0 9 1 * * (first day of month at 9 AM)">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty for manual generation only.</p>
        </div>

        <div>
            <label for="recipients_input" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recipients <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="text" id="recipients_input" value="{{ old('recipients') ? implode(', ', old('recipients')) : '' }}"
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none"
                placeholder="email1@example.com, email2@example.com"
                onchange="updateRecipients(this)">
            <div id="recipients_hidden"></div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Comma-separated email addresses to receive the report.</p>
        </div>

        <div class="flex items-center gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}
                class="w-4 h-4 rounded border-gray-300 dark:border-slate-600 text-violet-600 focus:ring-violet-500">
            <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-slate-700">
            <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Cancel</a>
            <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-violet-700 transition-colors">Create Template</button>
        </div>
    </form>
</div>

<script>
function updateRecipients(input) {
    const container = document.getElementById('recipients_hidden');
    container.innerHTML = '';
    const emails = input.value.split(',').map(e => e.trim()).filter(e => e);
    emails.forEach((email, index) => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = `recipients[${index}]`;
        hidden.value = email;
        container.appendChild(hidden);
    });
}
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('recipients_input');
    if (input.value) updateRecipients(input);
});
</script>
@endsection
