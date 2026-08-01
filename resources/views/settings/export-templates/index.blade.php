@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Export Templates') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Design your MOM layout with the block builder, then pick it when exporting.') }}</p>
        </div>
        <a href="{{ route('settings.export-templates.create') }}"
            class="bg-violet-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-violet-700 transition-colors">
            + {{ __('New Template') }}
        </a>
    </div>

    {{-- System presets --}}
    @php
        $systemPresets = \App\Domain\Export\Models\ExportTemplate::withoutGlobalScopes()->where('is_system', true)->get();
    @endphp
    @if($systemPresets->isNotEmpty())
    <div>
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('System Presets') }}</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($systemPresets as $preset)
            <div class="bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <p class="font-medium text-gray-900 dark:text-white text-sm">{{ $preset->name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $preset->description }}</p>
                </div>
                <form method="POST" action="{{ route('settings.export-templates.duplicate', $preset) }}">
                    @csrf
                    <button type="submit" class="text-violet-600 hover:text-violet-700 text-xs font-medium whitespace-nowrap ml-4">
                        {{ __('Salin & Edit') }}
                    </button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- User templates --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        @if($templates->isEmpty())
            <div class="px-6 py-12 text-center text-gray-500 dark:text-gray-400 text-sm">
                {{ __('No custom templates yet.') }}
                <span class="block mt-1">{{ __('Duplicate a system preset above, or') }}
                <a href="{{ route('settings.export-templates.create') }}" class="text-violet-600 hover:underline">{{ __('create a blank one') }}</a>.</span>
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-slate-700">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Name') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Blocks') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Default') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                    @foreach($templates as $template)
                        <tr>
                            <td class="px-6 py-4 text-gray-900 dark:text-white font-medium">
                                <div class="flex items-center gap-2">
                                    @if($template->logo_path)
                                        <img src="{{ asset('storage/'.$template->logo_path) }}" class="h-6 w-auto rounded" alt="">
                                    @endif
                                    <div>
                                        {{ $template->name }}
                                        @if($template->description)
                                            <p class="text-xs text-gray-500 dark:text-gray-400 font-normal mt-0.5">{{ $template->description }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                {{ is_array($template->blocks) ? count($template->blocks).' blok' : '—' }}
                            </td>
                            <td class="px-6 py-4">
                                @if($template->is_default)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400">{{ __('Default') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <a href="{{ route('settings.export-templates.builder', $template) }}"
                                    class="inline-flex items-center gap-1 bg-violet-600 hover:bg-violet-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                                    🧩 {{ __('Builder') }}
                                </a>
                                <a href="{{ route('settings.export-templates.edit', $template) }}"
                                    class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white text-sm">{{ __('nav.settings') }}</a>
                                <form method="POST" action="{{ route('settings.export-templates.destroy', $template) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-700 text-sm"
                                        onclick="event.preventDefault(); window.antaraConfirm('{{ __('Delete this template?') }}').then(ok => ok && this.closest('form').submit())">{{ __('Delete') }}</button>
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
