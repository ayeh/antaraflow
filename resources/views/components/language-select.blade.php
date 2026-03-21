@props(['name' => 'language', 'selected' => 'en'])

@php
$languages = [
    'en' => 'English',
    'ms' => 'Bahasa Melayu',
    'zh' => 'Chinese (中文)',
    'ta' => 'Tamil (தமிழ்)',
    'ja' => 'Japanese (日本語)',
    'ko' => 'Korean (한국어)',
    'fr' => 'French (Français)',
    'de' => 'German (Deutsch)',
    'es' => 'Spanish (Español)',
    'pt' => 'Portuguese (Português)',
    'ar' => 'Arabic (العربية)',
    'hi' => 'Hindi (हिन्दी)',
];
@endphp

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
        {{ __('Audio Language') }}
    </label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500']) }}
    >
        @foreach($languages as $code => $label)
            <option value="{{ $code }}" @selected($selected === $code)>{{ $label }}</option>
        @endforeach
    </select>
</div>
