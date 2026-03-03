@extends('admin.layouts.app')

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
    <form method="POST" action="{{ route('admin.branding.update') }}" class="max-w-4xl space-y-8">
        @csrf
        @method('PUT')

        {{-- Basic --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Basic</h3>
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="app_name" class="block text-sm font-medium text-slate-300 mb-1">App Name</label>
                    <input type="text" name="app_name" id="app_name" value="{{ old('app_name', $settings['app_name']) }}"
                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('app_name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="logo_url" class="block text-sm font-medium text-slate-300 mb-1">Logo URL</label>
                    <input type="text" name="logo_url" id="logo_url" value="{{ old('logo_url', $settings['logo_url']) }}"
                           placeholder="https://example.com/logo.png"
                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('logo_url') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="favicon_url" class="block text-sm font-medium text-slate-300 mb-1">Favicon URL</label>
                    <input type="text" name="favicon_url" id="favicon_url" value="{{ old('favicon_url', $settings['favicon_url']) }}"
                           placeholder="https://example.com/favicon.ico"
                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('favicon_url') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Colors --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Colors</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="primary_color" class="block text-sm font-medium text-slate-300 mb-1">Primary Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="primary_color" id="primary_color" value="{{ old('primary_color', $settings['primary_color']) }}"
                               class="h-10 w-14 rounded border border-slate-600 bg-slate-700 cursor-pointer"
                               oninput="document.getElementById('primary_color_hex').value = this.value">
                        <input type="text" id="primary_color_hex" value="{{ old('primary_color', $settings['primary_color']) }}"
                               class="flex-1 bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500"
                               oninput="document.getElementById('primary_color').value = this.value" readonly>
                    </div>
                    @error('primary_color') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="secondary_color" class="block text-sm font-medium text-slate-300 mb-1">Secondary Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="secondary_color" id="secondary_color" value="{{ old('secondary_color', $settings['secondary_color']) }}"
                               class="h-10 w-14 rounded border border-slate-600 bg-slate-700 cursor-pointer"
                               oninput="document.getElementById('secondary_color_hex').value = this.value">
                        <input type="text" id="secondary_color_hex" value="{{ old('secondary_color', $settings['secondary_color']) }}"
                               class="flex-1 bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500"
                               oninput="document.getElementById('secondary_color').value = this.value" readonly>
                    </div>
                    @error('secondary_color') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Contact</h3>
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="footer_text" class="block text-sm font-medium text-slate-300 mb-1">Footer Text</label>
                    <textarea name="footer_text" id="footer_text" rows="3"
                              class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">{{ old('footer_text', $settings['footer_text']) }}</textarea>
                    @error('footer_text') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="support_email" class="block text-sm font-medium text-slate-300 mb-1">Support Email</label>
                    <input type="email" name="support_email" id="support_email" value="{{ old('support_email', $settings['support_email']) }}"
                           placeholder="support@example.com"
                           class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('support_email') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Login Page --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Login Page</h3>
            <div>
                <label for="login_background_url" class="block text-sm font-medium text-slate-300 mb-1">Login Background Image URL</label>
                <input type="text" name="login_background_url" id="login_background_url" value="{{ old('login_background_url', $settings['login_background_url']) }}"
                       placeholder="https://example.com/background.jpg"
                       class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                @error('login_background_url') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Custom CSS --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Custom CSS</h3>
            <div>
                <label for="custom_css" class="block text-sm font-medium text-slate-300 mb-1">Custom CSS</label>
                <textarea name="custom_css" id="custom_css" rows="8"
                          class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500">{{ old('custom_css', $settings['custom_css']) }}</textarea>
                @error('custom_css') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Email Branding --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Email Branding</h3>
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="email_header_html" class="block text-sm font-medium text-slate-300 mb-1">Email Header HTML</label>
                    <textarea name="email_header_html" id="email_header_html" rows="5"
                              class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500">{{ old('email_header_html', $settings['email_header_html']) }}</textarea>
                    @error('email_header_html') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email_footer_html" class="block text-sm font-medium text-slate-300 mb-1">Email Footer HTML</label>
                    <textarea name="email_footer_html" id="email_footer_html" rows="5"
                              class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500">{{ old('email_footer_html', $settings['email_footer_html']) }}</textarea>
                    @error('email_footer_html') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Domain --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Domain</h3>
            <div>
                <label for="custom_domain" class="block text-sm font-medium text-slate-300 mb-1">Custom Domain</label>
                <input type="text" name="custom_domain" id="custom_domain" value="{{ old('custom_domain', $settings['custom_domain']) }}"
                       placeholder="app.yourdomain.com"
                       class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                @error('custom_domain') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                Save Branding
            </button>
        </div>
    </form>
@endsection
