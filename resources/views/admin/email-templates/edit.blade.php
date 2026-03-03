@extends('admin.layouts.app')

@section('title', 'Edit Template')
@section('page-title', 'Edit Template: ' . $emailTemplate->name)

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <a href="{{ route('admin.email-templates.index') }}" class="hover:text-white">Email Templates</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">Edit: {{ $emailTemplate->name }}</span>
    </nav>
@endsection

@section('content')
    <div x-data="{ previewSubject: '', previewBody: '', loading: false }">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Main Form --}}
            <div class="lg:col-span-2">
                <form method="POST" action="{{ route('admin.email-templates.update', $emailTemplate) }}" class="space-y-8">
                    @csrf
                    @method('PUT')

                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Template Details</h3>
                        <div class="space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Name</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $emailTemplate->name) }}"
                                       class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-xs text-slate-500">Slug: <span class="font-mono">{{ $emailTemplate->slug }}</span></p>
                                @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="subject" class="block text-sm font-medium text-slate-300 mb-1">Subject</label>
                                <input type="text" name="subject" id="subject" value="{{ old('subject', $emailTemplate->subject) }}"
                                       class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('subject') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="body_html" class="block text-sm font-medium text-slate-300 mb-1">Body HTML</label>
                                <textarea name="body_html" id="body_html" rows="20"
                                          class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500">{{ old('body_html', $emailTemplate->body_html) }}</textarea>
                                @error('body_html') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1"
                                           @checked(old('is_active', $emailTemplate->is_active))
                                           class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-0">
                                    <span class="text-sm text-slate-300">Active</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <button type="submit"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                            Save Template
                        </button>
                        <a href="{{ route('admin.email-templates.index') }}" class="text-sm text-slate-400 hover:text-white transition-colors">Cancel</a>
                    </div>
                </form>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Available Variables --}}
                @if(count($emailTemplate->variables ?? []) > 0)
                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Available Variables</h3>
                        <div class="space-y-2">
                            @foreach($emailTemplate->variables as $variable)
                                @php $variableTag = '{{' . $variable . '}}'; @endphp
                                <div x-data="{ copied: false }" class="flex items-center justify-between gap-2 p-2 bg-slate-700/50 rounded-lg">
                                    <code class="text-sm text-blue-400 font-mono">{{ $variableTag }}</code>
                                    <button type="button"
                                            @click="navigator.clipboard.writeText('{{ $variableTag }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="text-xs text-slate-400 hover:text-white transition-colors whitespace-nowrap">
                                        <span x-show="!copied">Copy</span>
                                        <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Preview Button --}}
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Preview</h3>
                    <p class="text-sm text-slate-400 mb-4">Preview the template with sample data.</p>
                    <button type="button"
                            @click="
                                loading = true;
                                fetch('{{ route('admin.email-templates.preview', $emailTemplate) }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                    },
                                })
                                .then(r => r.json())
                                .then(data => { previewSubject = data.subject; previewBody = data.body; loading = false; })
                                .catch(() => { loading = false; })
                            "
                            :disabled="loading"
                            class="w-full px-4 py-2.5 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors">
                        <span x-show="!loading">Preview with Sample Data</span>
                        <span x-show="loading">Loading...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Preview Panel --}}
        <div x-show="previewBody" x-cloak class="mt-8">
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white">Preview</h3>
                    <button type="button" @click="previewBody = ''; previewSubject = ''"
                            class="text-sm text-slate-400 hover:text-white transition-colors">Close</button>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-medium text-slate-400 mb-1">Subject:</h4>
                    <p class="text-white" x-text="previewSubject"></p>
                </div>

                <div>
                    <h4 class="text-sm font-medium text-slate-400 mb-1">Body:</h4>
                    <div class="bg-white rounded-lg p-6 text-slate-900" x-html="previewBody"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
