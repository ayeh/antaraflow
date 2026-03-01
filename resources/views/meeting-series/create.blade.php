@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('meeting-series.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Create Meeting Series</h1>
    </div>

    <form method="POST" action="{{ route('meeting-series.store') }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
        @csrf

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" id="description" rows="3" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('description') }}</textarea>
        </div>

        <div>
            <label for="recurrence_pattern" class="block text-sm font-medium text-gray-700 mb-1">Recurrence Pattern <span class="text-red-500">*</span></label>
            <select name="recurrence_pattern" id="recurrence_pattern" required class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
                <option value="">Select a pattern</option>
                <option value="weekly" {{ old('recurrence_pattern') === 'weekly' ? 'selected' : '' }}>Weekly</option>
                <option value="biweekly" {{ old('recurrence_pattern') === 'biweekly' ? 'selected' : '' }}>Bi-weekly</option>
                <option value="monthly" {{ old('recurrence_pattern') === 'monthly' ? 'selected' : '' }}>Monthly</option>
            </select>
        </div>

        <div class="flex items-center gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
            <label for="is_active" class="text-sm font-medium text-gray-700">Active</label>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
            <a href="{{ route('meeting-series.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Cancel</a>
            <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Create Series</button>
        </div>
    </form>
</div>
@endsection
