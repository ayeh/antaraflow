@extends('admin.layouts.app')
@php use Illuminate\Support\Facades\Storage; @endphp

@section('title', 'Platform Branding')
@section('page-title', 'Platform Branding')

@section('breadcrumbs')
    <nav class="text-sm text-slate-400 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">Dashboard</a>
        <span class="mx-1">/</span>
        <span class="text-slate-200">Branding</span>
    </nav>
@endsection

@section('content')
<div
    x-data="brandingForm()"
    x-init="init()"
    class="flex gap-8 items-start"
>
    {{-- Left: Form --}}
    <div class="flex-1 min-w-0 space-y-6">

        {{-- Theme Presets --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">Theme Presets</h3>
                <div class="flex gap-2">
                    <button type="button" @click="resetToDefaults()"
                            class="px-3 py-1.5 text-xs font-medium text-slate-300 bg-slate-700 hover:bg-slate-600 rounded-lg transition-colors">
                        Reset to Default
                    </button>
                    <button type="button" @click="saveCurrentAsPreset()"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition-colors">
                        Save Current as Theme
                    </button>
                </div>
            </div>

            {{-- Built-in presets --}}
            <p class="text-xs text-slate-400 mb-3">Built-in</p>
            <div class="flex flex-wrap gap-3 mb-4">
                <template x-for="preset in builtInPresets" :key="preset.name">
                    <button type="button" @click="applyPreset(preset)"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-600 hover:border-slate-400 transition-colors bg-slate-700/50 text-sm text-slate-200">
                        <span class="w-4 h-4 rounded-full border border-white/20 flex-shrink-0" :style="`background:${preset.primary_color}`"></span>
                        <span x-text="preset.name"></span>
                    </button>
                </template>
            </div>

            {{-- Custom presets --}}
            <template x-if="customPresets.length > 0">
                <div>
                    <p class="text-xs text-slate-400 mb-3">Custom</p>
                    <div class="flex flex-wrap gap-3">
                        <template x-for="preset in customPresets" :key="preset.name">
                            <div class="flex items-center gap-1 px-3 py-2 rounded-lg border border-slate-600 bg-slate-700/50">
                                <button type="button" @click="applyPreset(preset)"
                                        class="flex items-center gap-2 text-sm text-slate-200">
                                    <span class="w-4 h-4 rounded-full border border-white/20 flex-shrink-0" :style="`background:${preset.primary_color}`"></span>
                                    <span x-text="preset.name"></span>
                                </button>
                                <button type="button" @click="deletePreset(preset.name)"
                                        class="ml-1 text-slate-500 hover:text-red-400 transition-colors text-xs leading-none">✕</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- The form --}}
        <form method="POST" action="{{ route('admin.branding.update') }}"
              enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Basic --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Basic</h3>
                <div class="space-y-5">
                    <div>
                        <label for="app_name" class="block text-sm font-medium text-slate-300 mb-1">App Name</label>
                        <input type="text" name="app_name" id="app_name"
                               :value="form.app_name"
                               @input="form.app_name = $event.target.value"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-violet-500 focus:border-violet-500">
                        @error('app_name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Logo Upload --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Logo</label>
                        <div class="flex items-start gap-4">
                            <div x-show="form.logo_preview || '{{ $settings['logo_path'] ? Storage::url($settings['logo_path']) : $settings['logo_url'] }}'" class="flex-shrink-0">
                                <img :src="form.logo_preview || '{{ $settings['logo_path'] ? Storage::url($settings['logo_path']) : $settings['logo_url'] }}'"
                                     class="h-16 w-auto max-w-32 rounded-lg border border-slate-600 object-contain bg-slate-700 p-1">
                            </div>
                            <div class="flex-1 space-y-2">
                                <label class="flex flex-col items-center justify-center w-full h-20 border-2 border-dashed border-slate-600 rounded-lg cursor-pointer hover:border-violet-500 transition-colors bg-slate-700/30">
                                    <span class="text-sm text-slate-400">Click to upload or drag & drop</span>
                                    <span class="text-xs text-slate-500 mt-0.5">PNG, JPG, SVG — max 2MB</span>
                                    <input type="file" name="logo" accept="image/*" class="hidden"
                                           @change="handleFilePreview($event, 'logo_preview')">
                                </label>
                                <input type="text" name="logo_url" placeholder="Or paste URL: https://example.com/logo.png"
                                       value="{{ old('logo_url', $settings['logo_url']) }}"
                                       class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-xs placeholder-slate-500">
                            </div>
                        </div>
                        @error('logo') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Favicon Upload --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Favicon</label>
                        <div class="flex items-start gap-4">
                            <div x-show="form.favicon_preview || '{{ $settings['favicon_path'] ? Storage::url($settings['favicon_path']) : $settings['favicon_url'] }}'" class="flex-shrink-0">
                                <img :src="form.favicon_preview || '{{ $settings['favicon_path'] ? Storage::url($settings['favicon_path']) : $settings['favicon_url'] }}'"
                                     class="h-8 w-8 rounded border border-slate-600 object-contain bg-slate-700 p-0.5">
                            </div>
                            <div class="flex-1 space-y-2">
                                <label class="flex flex-col items-center justify-center w-full h-16 border-2 border-dashed border-slate-600 rounded-lg cursor-pointer hover:border-violet-500 transition-colors bg-slate-700/30">
                                    <span class="text-sm text-slate-400">Upload favicon</span>
                                    <span class="text-xs text-slate-500">ICO, PNG — max 2MB, 32×32 ideal</span>
                                    <input type="file" name="favicon" accept="image/*,.ico" class="hidden"
                                           @change="handleFilePreview($event, 'favicon_preview')">
                                </label>
                                <input type="text" name="favicon_url" placeholder="Or paste URL: https://example.com/favicon.ico"
                                       value="{{ old('favicon_url', $settings['favicon_url']) }}"
                                       class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-xs placeholder-slate-500">
                            </div>
                        </div>
                        @error('favicon') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Colors --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Colors</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach([
                        ['key' => 'primary_color',   'label' => 'Primary',   'alpineKey' => 'primary_color'],
                        ['key' => 'secondary_color',  'label' => 'Secondary', 'alpineKey' => 'secondary_color'],
                        ['key' => 'accent_color',     'label' => 'Accent',    'alpineKey' => 'accent_color'],
                        ['key' => 'danger_color',     'label' => 'Danger',    'alpineKey' => 'danger_color'],
                        ['key' => 'success_color',    'label' => 'Success',   'alpineKey' => 'success_color'],
                    ] as $colorField)
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">{{ $colorField['label'] }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="{{ $colorField['key'] }}"
                                   x-model="form.{{ $colorField['alpineKey'] }}"
                                   class="h-10 w-12 rounded border border-slate-600 bg-slate-700 cursor-pointer p-0.5 flex-shrink-0">
                            <input type="text"
                                   x-model="form.{{ $colorField['alpineKey'] }}"
                                   class="flex-1 bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono"
                                   maxlength="7" placeholder="#000000">
                        </div>
                        @error($colorField['key']) <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Typography --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Typography</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    @foreach([
                        ['key' => 'heading_font', 'label' => 'Heading Font', 'alpineKey' => 'heading_font'],
                        ['key' => 'body_font',    'label' => 'Body Font',    'alpineKey' => 'body_font'],
                    ] as $fontField)
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">{{ $fontField['label'] }}</label>
                        <select name="{{ $fontField['key'] }}"
                                x-model="form.{{ $fontField['alpineKey'] }}"
                                class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm">
                            @foreach(['Inter','Poppins','Roboto','Lato','Montserrat','Open Sans','Nunito','Raleway','Source Sans Pro','DM Sans'] as $fontName)
                                <option value="{{ $fontName }}"
                                    {{ old($fontField['key'], $settings[$fontField['key']]) === $fontName ? 'selected' : '' }}>
                                    {{ $fontName }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Login Page --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Login Page</h3>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Background Image</label>
                    <div class="flex items-start gap-4">
                        <div x-show="form.bg_preview || '{{ $settings['login_background_path'] ? Storage::url($settings['login_background_path']) : $settings['login_background_url'] }}'" class="flex-shrink-0">
                            <img :src="form.bg_preview || '{{ $settings['login_background_path'] ? Storage::url($settings['login_background_path']) : $settings['login_background_url'] }}'"
                                 class="h-20 w-32 rounded-lg border border-slate-600 object-cover">
                        </div>
                        <div class="flex-1 space-y-2">
                            <label class="flex flex-col items-center justify-center w-full h-20 border-2 border-dashed border-slate-600 rounded-lg cursor-pointer hover:border-violet-500 transition-colors bg-slate-700/30">
                                <span class="text-sm text-slate-400">Click to upload background</span>
                                <span class="text-xs text-slate-500">JPG, PNG — max 5MB</span>
                                <input type="file" name="login_background" accept="image/*" class="hidden"
                                       @change="handleFilePreview($event, 'bg_preview')">
                            </label>
                            <input type="text" name="login_background_url" placeholder="Or paste URL"
                                   value="{{ old('login_background_url', $settings['login_background_url']) }}"
                                   class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-xs placeholder-slate-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contact & Footer --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Contact & Footer</h3>
                <div class="space-y-5">
                    <div>
                        <label for="footer_text" class="block text-sm font-medium text-slate-300 mb-1">Footer Text</label>
                        <textarea name="footer_text" id="footer_text" rows="3"
                                  class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm">{{ old('footer_text', $settings['footer_text']) }}</textarea>
                    </div>
                    <div>
                        <label for="support_email" class="block text-sm font-medium text-slate-300 mb-1">Support Email</label>
                        <input type="email" name="support_email" id="support_email"
                               value="{{ old('support_email', $settings['support_email']) }}"
                               placeholder="support@example.com"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
            </div>

            {{-- Advanced --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Advanced</h3>
                <div class="space-y-5">
                    <div>
                        <label for="custom_domain" class="block text-sm font-medium text-slate-300 mb-1">Custom Domain</label>
                        <input type="text" name="custom_domain" id="custom_domain"
                               value="{{ old('custom_domain', $settings['custom_domain']) }}"
                               placeholder="app.yourdomain.com"
                               class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="custom_css" class="block text-sm font-medium text-slate-300 mb-1">Custom CSS</label>
                        <textarea name="custom_css" id="custom_css" rows="8"
                                  class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono">{{ old('custom_css', $settings['custom_css']) }}</textarea>
                    </div>
                    <div>
                        <label for="email_header_html" class="block text-sm font-medium text-slate-300 mb-1">Email Header HTML</label>
                        <textarea name="email_header_html" id="email_header_html" rows="4"
                                  class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono">{{ old('email_header_html', $settings['email_header_html']) }}</textarea>
                    </div>
                    <div>
                        <label for="email_footer_html" class="block text-sm font-medium text-slate-300 mb-1">Email Footer HTML</label>
                        <textarea name="email_footer_html" id="email_footer_html" rows="4"
                                  class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono">{{ old('email_footer_html', $settings['email_footer_html']) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-4 pb-8">
                <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    Save Branding
                </button>
            </div>
        </form>
    </div>

    {{-- Right: Live Preview (sticky) --}}
    <div class="w-80 flex-shrink-0 sticky top-8 space-y-4">
        <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden">
            <div class="flex border-b border-slate-700">
                <button type="button" @click="previewTab = 'sidebar'"
                        :class="previewTab === 'sidebar' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'"
                        class="flex-1 px-4 py-3 text-xs font-medium transition-colors">
                    App Sidebar
                </button>
                <button type="button" @click="previewTab = 'login'"
                        :class="previewTab === 'login' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'"
                        class="flex-1 px-4 py-3 text-xs font-medium transition-colors">
                    Login Page
                </button>
            </div>

            {{-- Sidebar preview --}}
            <div x-show="previewTab === 'sidebar'" class="p-3">
                <div class="rounded-lg overflow-hidden border border-slate-600" style="height:300px;">
                    <div class="flex h-full">
                        <div class="w-32 flex flex-col" :style="`background-color:${form.primary_color}22;border-right:1px solid ${form.primary_color}44`">
                            <div class="p-3 border-b" :style="`border-color:${form.primary_color}44`">
                                <template x-if="form.logo_preview">
                                    <img :src="form.logo_preview" class="h-5 w-auto object-contain">
                                </template>
                                <template x-if="!form.logo_preview">
                                    <span class="text-xs font-bold text-white truncate block" x-text="form.app_name || 'antaraFLOW'"></span>
                                </template>
                            </div>
                            <div class="flex-1 p-2 space-y-1">
                                <template x-for="item in ['Dashboard','Users','Settings']" :key="item">
                                    <div class="px-2 py-1.5 rounded text-xs text-white/60" x-text="item"></div>
                                </template>
                                <div class="px-2 py-1.5 rounded text-xs text-white font-medium"
                                     :style="`background-color:${form.primary_color}55`">Active Page</div>
                            </div>
                        </div>
                        <div class="flex-1 bg-slate-900 p-3">
                            <div class="h-3 w-24 rounded bg-slate-700 mb-3"></div>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div class="h-12 rounded-lg" :style="`background-color:${form.primary_color}22;border:1px solid ${form.primary_color}44`"></div>
                                <div class="h-12 rounded-lg" :style="`background-color:${form.accent_color}22;border:1px solid ${form.accent_color}44`"></div>
                            </div>
                            <div class="space-y-2">
                                <div class="h-2 w-full rounded bg-slate-700"></div>
                                <div class="h-2 w-3/4 rounded bg-slate-700"></div>
                                <div class="h-2 w-1/2 rounded bg-slate-700"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Login preview --}}
            <div x-show="previewTab === 'login'" class="p-3">
                <div class="rounded-lg overflow-hidden border border-slate-600 relative" style="height:300px;">
                    <div class="absolute inset-0"
                         :style="form.bg_preview ? `background-image:url(${form.bg_preview});background-size:cover;background-position:center;filter:blur(2px) brightness(0.4)` : 'background-color:#0f172a'">
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center p-4">
                        <div class="w-full max-w-xs bg-slate-800/90 rounded-xl p-4 border border-slate-700">
                            <div class="text-center mb-3">
                                <template x-if="form.logo_preview">
                                    <img :src="form.logo_preview" class="h-8 w-auto object-contain mx-auto mb-2">
                                </template>
                                <p class="text-xs font-bold text-white" x-text="form.app_name || 'antaraFLOW'"></p>
                            </div>
                            <div class="space-y-2 mb-3">
                                <div class="h-7 rounded bg-slate-700 border border-slate-600"></div>
                                <div class="h-7 rounded bg-slate-700 border border-slate-600"></div>
                            </div>
                            <div class="h-7 rounded flex items-center justify-center text-xs font-medium text-white"
                                 :style="`background-color:${form.primary_color}`">Sign In</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Color chips --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
            <p class="text-xs text-slate-400 mb-3">Color Palette</p>
            <div class="flex gap-2 flex-wrap">
                <template x-for="[label, key] in [['P','primary_color'],['S','secondary_color'],['A','accent_color'],['D','danger_color'],['✓','success_color']]" :key="label">
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-8 h-8 rounded-full border-2 border-white/10 shadow" :style="`background-color:${form[key]}`"></div>
                        <span class="text-xs text-slate-500" x-text="label"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function brandingForm() {
    return {
        previewTab: 'sidebar',

        form: {
            app_name: @json($settings['app_name']),
            primary_color: @json($settings['primary_color']),
            secondary_color: @json($settings['secondary_color']),
            accent_color: @json($settings['accent_color']),
            danger_color: @json($settings['danger_color']),
            success_color: @json($settings['success_color']),
            heading_font: @json($settings['heading_font']),
            body_font: @json($settings['body_font']),
            logo_preview: null,
            favicon_preview: null,
            bg_preview: null,
        },

        builtInPresets: [
            { name: 'Default Purple', primary_color: '#7c3aed', secondary_color: '#3b82f6', accent_color: '#10b981', danger_color: '#ef4444', success_color: '#22c55e', heading_font: 'Inter', body_font: 'Inter' },
            { name: 'Ocean Blue',     primary_color: '#0ea5e9', secondary_color: '#06b6d4', accent_color: '#f59e0b', danger_color: '#ef4444', success_color: '#22c55e', heading_font: 'Poppins', body_font: 'Inter' },
            { name: 'Forest Green',  primary_color: '#16a34a', secondary_color: '#15803d', accent_color: '#3b82f6', danger_color: '#ef4444', success_color: '#22c55e', heading_font: 'Nunito', body_font: 'Nunito' },
            { name: 'Sunset Orange', primary_color: '#ea580c', secondary_color: '#dc2626', accent_color: '#7c3aed', danger_color: '#ef4444', success_color: '#22c55e', heading_font: 'Montserrat', body_font: 'Inter' },
            { name: 'Minimal Dark',  primary_color: '#374151', secondary_color: '#6b7280', accent_color: '#f3f4f6', danger_color: '#ef4444', success_color: '#22c55e', heading_font: 'Inter', body_font: 'Inter' },
        ],

        customPresets: @json(is_array(json_decode($settings['custom_themes'] ?? '[]')) ? json_decode($settings['custom_themes'] ?? '[]', true) : []),

        defaults: {
            app_name: 'antaraFLOW',
            primary_color: '#7c3aed',
            secondary_color: '#3b82f6',
            accent_color: '#10b981',
            danger_color: '#ef4444',
            success_color: '#22c55e',
            heading_font: 'Inter',
            body_font: 'Inter',
        },

        init() {},

        handleFilePreview(event, previewKey) {
            const file = event.target.files[0];
            if (!file) { return; }
            const reader = new FileReader();
            reader.onload = (e) => { this.form[previewKey] = e.target.result; };
            reader.readAsDataURL(file);
        },

        applyPreset(preset) {
            const keys = ['primary_color','secondary_color','accent_color','danger_color','success_color','heading_font','body_font'];
            keys.forEach(key => {
                if (preset[key]) { this.form[key] = preset[key]; }
            });
        },

        resetToDefaults() {
            Object.assign(this.form, this.defaults);
        },

        async saveCurrentAsPreset() {
            const name = prompt('Enter a name for this theme:');
            if (!name || !name.trim()) { return; }

            const payload = {
                name: name.trim(),
                primary_color: this.form.primary_color,
                secondary_color: this.form.secondary_color,
                accent_color: this.form.accent_color,
                danger_color: this.form.danger_color,
                success_color: this.form.success_color,
                heading_font: this.form.heading_font,
                body_font: this.form.body_font,
            };

            const response = await fetch('{{ route('admin.branding.presets.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            if (response.ok) {
                this.customPresets = this.customPresets.filter(p => p.name !== name.trim());
                this.customPresets.push(payload);
            }
        },

        async deletePreset(name) {
            if (!confirm(`Delete theme "${name}"?`)) { return; }

            const response = await fetch(`{{ url('admin/branding/presets') }}/${encodeURIComponent(name)}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });

            if (response.ok) {
                this.customPresets = this.customPresets.filter(p => p.name !== name);
            }
        },
    };
}
</script>
@endsection
