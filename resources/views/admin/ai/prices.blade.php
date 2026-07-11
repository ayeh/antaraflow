@extends('admin.layouts.app')

@section('title', __('AI Model Pricing'))
@section('page-title', __('AI Model Pricing'))

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">{{ __('Dashboard') }}</a>
        <span class="mx-1">/</span>
        <a href="{{ route('admin.ai.index') }}" class="hover:text-white">{{ __('AI Control') }}</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">{{ __('Model Pricing') }}</span>
    </nav>
@endsection

@section('content')
    @php $inp = 'w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-2 py-1.5 text-xs focus:ring-blue-500 focus:border-blue-500'; @endphp

    <div class="max-w-6xl space-y-6">
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <p class="text-sm text-slate-400">
                {{ __('Prices in USD per 1M tokens (transcription: per audio minute). A call is matched by exact model name first, then by regex patterns in priority order; the first match wins. Custom rows override the built-in seeds.') }}
            </p>
        </div>

        {{-- Add new --}}
        <form method="POST" action="{{ route('admin.ai.prices.store') }}" class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            @csrf
            <h3 class="text-base font-semibold text-white mb-3">{{ __('Add a model price') }}</h3>
            <div class="grid grid-cols-2 md:grid-cols-8 gap-3 items-end">
                <div class="col-span-2"><label class="block text-xs text-slate-400 mb-1">{{ __('Pattern / model') }}</label><input name="pattern" value="{{ old('pattern') }}" placeholder="gpt-5.4-mini" class="{{ $inp }}"></div>
                <div><label class="block text-xs text-slate-400 mb-1">{{ __('Provider') }}</label><input name="provider" value="{{ old('provider', 'openai') }}" class="{{ $inp }}"></div>
                <div><label class="block text-xs text-slate-400 mb-1">{{ __('Input') }}</label><input name="input_per_mtok" type="number" step="0.0001" value="{{ old('input_per_mtok') }}" class="{{ $inp }}"></div>
                <div><label class="block text-xs text-slate-400 mb-1">{{ __('Output') }}</label><input name="output_per_mtok" type="number" step="0.0001" value="{{ old('output_per_mtok') }}" class="{{ $inp }}"></div>
                <div><label class="block text-xs text-slate-400 mb-1">{{ __('Cached in') }}</label><input name="cached_input_per_mtok" type="number" step="0.0001" value="{{ old('cached_input_per_mtok') }}" class="{{ $inp }}"></div>
                <div><label class="block text-xs text-slate-400 mb-1">{{ __('Per min') }}</label><input name="per_minute" type="number" step="0.0001" value="{{ old('per_minute') }}" class="{{ $inp }}"></div>
                <div><label class="block text-xs text-slate-400 mb-1">{{ __('Priority') }}</label><input name="priority" type="number" value="{{ old('priority', 200) }}" class="{{ $inp }}"></div>
            </div>
            <div class="flex items-center gap-4 mt-3">
                <label class="flex items-center gap-2 text-xs text-slate-300"><input type="checkbox" name="is_regex" value="1" @checked(old('is_regex')) class="rounded border-slate-600 bg-slate-700 text-blue-500"> {{ __('Pattern is a regex') }}</label>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">{{ __('Add price') }}</button>
                @error('pattern') <span class="text-sm text-red-400">{{ $message }}</span> @enderror
            </div>
        </form>

        {{-- Existing --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <h3 class="text-base font-semibold text-white mb-4">{{ __('Registry') }} ({{ $prices->count() }})</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700">
                            <th class="py-2 pr-2 font-medium">{{ __('Pattern') }}</th>
                            <th class="py-2 pr-2 font-medium">{{ __('Provider') }}</th>
                            <th class="py-2 pr-2 font-medium">{{ __('Regex') }}</th>
                            <th class="py-2 pr-2 font-medium">{{ __('Input') }}</th>
                            <th class="py-2 pr-2 font-medium">{{ __('Output') }}</th>
                            <th class="py-2 pr-2 font-medium">{{ __('Cached') }}</th>
                            <th class="py-2 pr-2 font-medium">{{ __('Per min') }}</th>
                            <th class="py-2 pr-2 font-medium">{{ __('Prio') }}</th>
                            <th class="py-2 font-medium text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($prices as $p)
                            <tr class="border-b border-slate-700/50 text-slate-200">
                                <td class="py-2 pr-2"><input form="pe{{ $p->id }}" name="pattern" value="{{ $p->pattern }}" class="{{ $inp }} font-mono" style="min-width:150px"></td>
                                <td class="py-2 pr-2"><input form="pe{{ $p->id }}" name="provider" value="{{ $p->provider }}" class="{{ $inp }}" style="min-width:80px"></td>
                                <td class="py-2 pr-2"><input form="pe{{ $p->id }}" type="checkbox" name="is_regex" value="1" @checked($p->is_regex) class="rounded border-slate-600 bg-slate-700 text-blue-500"></td>
                                <td class="py-2 pr-2"><input form="pe{{ $p->id }}" name="input_per_mtok" type="number" step="0.0001" value="{{ $p->input_per_mtok }}" class="{{ $inp }}" style="min-width:70px"></td>
                                <td class="py-2 pr-2"><input form="pe{{ $p->id }}" name="output_per_mtok" type="number" step="0.0001" value="{{ $p->output_per_mtok }}" class="{{ $inp }}" style="min-width:70px"></td>
                                <td class="py-2 pr-2"><input form="pe{{ $p->id }}" name="cached_input_per_mtok" type="number" step="0.0001" value="{{ $p->cached_input_per_mtok }}" class="{{ $inp }}" style="min-width:70px"></td>
                                <td class="py-2 pr-2"><input form="pe{{ $p->id }}" name="per_minute" type="number" step="0.0001" value="{{ $p->per_minute }}" class="{{ $inp }}" style="min-width:70px"></td>
                                <td class="py-2 pr-2"><input form="pe{{ $p->id }}" name="priority" type="number" value="{{ $p->priority }}" class="{{ $inp }}" style="min-width:56px"></td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <button type="submit" form="pe{{ $p->id }}" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">{{ __('Save') }}</button>
                                    <button type="submit" form="pd{{ $p->id }}" class="px-3 py-1.5 bg-slate-700 hover:bg-red-700 text-white rounded-lg">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-4 text-center text-slate-500">{{ __('No prices in the registry.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Row edit/delete forms (referenced via the form= attribute) --}}
        @foreach($prices as $p)
            <form id="pe{{ $p->id }}" method="POST" action="{{ route('admin.ai.prices.update', $p) }}" class="hidden">@csrf @method('PUT')</form>
            <form id="pd{{ $p->id }}" method="POST" action="{{ route('admin.ai.prices.destroy', $p) }}" class="hidden">@csrf @method('DELETE')</form>
        @endforeach
    </div>
@endsection
