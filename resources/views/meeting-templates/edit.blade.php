@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('meeting-templates.show', $meetingTemplate) }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Edit Template</h1>
    </div>

    <form method="POST" action="{{ route('meeting-templates.update', $meetingTemplate) }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
        @csrf
        @method('PUT')

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
            <input type="text" name="name" id="name" value="{{ old('name', $meetingTemplate->name) }}" required class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" id="description" rows="3" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('description', $meetingTemplate->description) }}</textarea>
        </div>

        <div>
            <label for="structure" class="block text-sm font-medium text-gray-700 mb-1">Structure <span class="text-red-500">*</span></label>
            <textarea name="structure" id="structure" rows="8" required class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('structure', json_encode($meetingTemplate->structure, JSON_PRETTY_PRINT)) }}</textarea>
            <p class="mt-1 text-xs text-gray-500">Enter JSON structure. Example: <code class="bg-gray-100 px-1 rounded">{"sections": [{"title": "Agenda", "type": "text"}]}</code></p>
        </div>

        <div>
            <label for="default_settings" class="block text-sm font-medium text-gray-700 mb-1">Default Settings <span class="text-gray-400 font-normal">(optional)</span></label>
            <textarea name="default_settings" id="default_settings" rows="4" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-mono focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none">{{ old('default_settings', $meetingTemplate->default_settings ? json_encode($meetingTemplate->default_settings, JSON_PRETTY_PRINT) : '') }}</textarea>
            <p class="mt-1 text-xs text-gray-500">Enter JSON for default settings (optional).</p>
        </div>

        <div class="space-y-3">
            <div class="flex items-center gap-3">
                <input type="hidden" name="is_shared" value="0">
                <input type="checkbox" name="is_shared" id="is_shared" value="1" {{ old('is_shared', $meetingTemplate->is_shared) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                <label for="is_shared" class="text-sm font-medium text-gray-700">Shared with organization</label>
            </div>
            <div class="flex items-center gap-3">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" id="is_default" value="1" {{ old('is_default', $meetingTemplate->is_default) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                <label for="is_default" class="text-sm font-medium text-gray-700">Set as default template</label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
            <a href="{{ route('meeting-templates.show', $meetingTemplate) }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Cancel</a>
            <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">Update Template</button>
        </div>
    </form>
</div>
@endsection
