@extends('admin.layouts.app')

@section('title', 'Edit Plan')
@section('page-title', 'Edit Plan: ' . $plan->name)

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <a href="{{ route('admin.plans.index') }}" class="hover:text-white">Subscription Plans</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">Edit: {{ $plan->name }}</span>
    </nav>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="max-w-4xl space-y-8">
        @csrf
        @method('PUT')

        {{-- Basic Information --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Basic Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Plan Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $plan->name) }}"
                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="slug" class="block text-sm font-medium text-slate-300 mb-1">Slug</label>
                    <input type="text" name="slug" id="slug" value="{{ old('slug', $plan->slug) }}"
                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('slug') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-slate-300 mb-1">Description</label>
                    <textarea name="description" id="description" rows="3"
                              class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description', $plan->description) }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Pricing --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Pricing</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="price_monthly" class="block text-sm font-medium text-slate-300 mb-1">Monthly Price (RM)</label>
                    <input type="number" name="price_monthly" id="price_monthly" value="{{ old('price_monthly', $plan->price_monthly) }}" step="0.01" min="0"
                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('price_monthly') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="price_yearly" class="block text-sm font-medium text-slate-300 mb-1">Yearly Price (RM)</label>
                    <input type="number" name="price_yearly" id="price_yearly" value="{{ old('price_yearly', $plan->price_yearly) }}" step="0.01" min="0"
                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('price_yearly') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Features --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Features</h3>
            @error('features') <p class="mb-3 text-sm text-red-400">{{ $message }}</p> @enderror
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @php
                    $featureList = [
                        'export' => 'Export',
                        'ai_summaries' => 'AI Summaries',
                        'custom_branding' => 'Custom Branding',
                        'api_access' => 'API Access',
                        'advanced_analytics' => 'Advanced Analytics',
                        'priority_support' => 'Priority Support',
                    ];
                    $planFeatures = $plan->features ?? [];
                @endphp
                @foreach($featureList as $key => $label)
                    <label class="flex items-center gap-3 p-3 bg-slate-700/50 rounded-lg cursor-pointer hover:bg-slate-700 transition-colors">
                        <input type="hidden" name="features[{{ $key }}]" value="0">
                        <input type="checkbox" name="features[{{ $key }}]" value="1"
                               {{ old("features.$key", $planFeatures[$key] ?? false) ? 'checked' : '' }}
                               class="w-4 h-4 rounded bg-slate-600 border-slate-500 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-slate-200">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Limits --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Limits</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @php
                    $limits = [
                        'max_users' => ['label' => 'Max Users', 'value' => $plan->max_users],
                        'max_meetings_per_month' => ['label' => 'Max Meetings / Month', 'value' => $plan->max_meetings_per_month],
                        'max_audio_minutes_per_month' => ['label' => 'Max Audio Minutes / Month', 'value' => $plan->max_audio_minutes_per_month],
                        'max_storage_mb' => ['label' => 'Max Storage (MB)', 'value' => $plan->max_storage_mb],
                    ];
                @endphp
                @foreach($limits as $field => $config)
                    @php $currentValue = old($field, $config['value']); @endphp
                    <div x-data="{ unlimited: {{ $currentValue == -1 ? 'true' : 'false' }} }">
                        <label class="block text-sm font-medium text-slate-300 mb-1">{{ $config['label'] }}</label>
                        <label class="flex items-center gap-2 mb-2">
                            <input type="checkbox" x-model="unlimited"
                                   class="w-4 h-4 rounded bg-slate-600 border-slate-500 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-slate-400">Unlimited</span>
                        </label>
                        <input type="number" name="{{ $field }}"
                               x-bind:value="unlimited ? -1 : {{ $currentValue == -1 ? 10 : $currentValue }}"
                               x-bind:disabled="unlimited"
                               x-show="!unlimited"
                               min="0"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <template x-if="unlimited">
                            <input type="hidden" name="{{ $field }}" value="-1">
                        </template>
                        @error($field) <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Settings --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Settings</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="sort_order" class="block text-sm font-medium text-slate-300 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $plan->sort_order) }}" min="0"
                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('sort_order') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-3 p-3 bg-slate-700/50 rounded-lg cursor-pointer hover:bg-slate-700 transition-colors">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', $plan->is_active) ? 'checked' : '' }}
                               class="w-4 h-4 rounded bg-slate-600 border-slate-500 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-slate-200">Active</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                Update Plan
            </button>
            <a href="{{ route('admin.plans.index') }}" class="text-sm text-slate-400 hover:text-white transition-colors">Cancel</a>
        </div>
    </form>
@endsection
