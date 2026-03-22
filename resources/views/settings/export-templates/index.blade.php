@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Export Templates</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage PDF and document export templates for your organization</p>
        </div>
        <a href="{{ route('settings.export-templates.create') }}"
            class="bg-violet-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-violet-700 transition-colors">
            New Template
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        @if($templates->isEmpty())
            <div class="px-6 py-12 text-center text-gray-500 dark:text-gray-400 text-sm">
                No export templates yet. <a href="{{ route('settings.export-templates.create') }}" class="text-violet-600 hover:underline">Create your first template</a>.
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-slate-700">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Primary Color</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Font</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Default</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                    @foreach($templates as $template)
                        <tr>
                            <td class="px-6 py-4 text-gray-900 dark:text-white font-medium">
                                {{ $template->name }}
                                @if($template->description)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-normal mt-0.5">{{ $template->description }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($template->primary_color)
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded border border-gray-300 dark:border-slate-600" style="background-color: {{ $template->primary_color }}"></div>
                                        <span class="text-gray-600 dark:text-gray-300 text-xs">{{ $template->primary_color }}</span>
                                    </div>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ $template->font_family ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if($template->is_default)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400">Default</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <a href="{{ route('settings.export-templates.edit', $template) }}"
                                    class="text-violet-600 hover:text-violet-700 text-sm font-medium">Edit</a>
                                <form method="POST" action="{{ route('settings.export-templates.destroy', $template) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-700 text-sm font-medium"
                                        onclick="return confirm('Delete this template?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
