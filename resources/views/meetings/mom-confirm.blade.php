<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $meeting->title }} — Pengesahan Minit</title>
    @php
        $faviconSrc = $branding->get('favicon_path') ? Storage::url($branding->get('favicon_path')) : ($branding->get('favicon_url') ?: asset('favicon.ico'));
    @endphp
    <link rel="icon" href="{{ $faviconSrc }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --brand-primary: {{ $branding->get('primary_color', '#7c3aed') }};
            --brand-p50:  color-mix(in srgb, var(--brand-primary)  5%, white);
            --brand-p100: color-mix(in srgb, var(--brand-primary) 10%, white);
            --brand-p200: color-mix(in srgb, var(--brand-primary) 20%, white);
            --brand-p300: color-mix(in srgb, var(--brand-primary) 40%, white);
            --brand-p400: color-mix(in srgb, var(--brand-primary) 60%, white);
            --brand-p500: color-mix(in srgb, var(--brand-primary) 80%, white);
            --brand-p600: var(--brand-primary);
            --brand-p700: color-mix(in srgb, var(--brand-primary) 85%, black);
            --brand-p800: color-mix(in srgb, var(--brand-primary) 70%, black);
            --brand-p900: color-mix(in srgb, var(--brand-primary) 15%, #0f172a);
        }
        .bg-violet-50  { background-color: var(--brand-p50)  !important; }
        .bg-violet-100 { background-color: var(--brand-p100) !important; }
        .bg-violet-200 { background-color: var(--brand-p200) !important; }
        .bg-violet-600 { background-color: var(--brand-p600) !important; }
        .bg-violet-700 { background-color: var(--brand-p700) !important; }
        .hover\:bg-violet-600:hover { background-color: var(--brand-p600) !important; }
        .hover\:bg-violet-700:hover { background-color: var(--brand-p700) !important; }
        .text-violet-600 { color: var(--brand-p600) !important; }
        .text-violet-700 { color: var(--brand-p700) !important; }
        .text-violet-800 { color: var(--brand-p800) !important; }
        .border-violet-100 { border-color: var(--brand-p100) !important; }
        .focus\:ring-violet-500:focus { --tw-ring-color: var(--brand-p500) !important; }
        .focus\:border-violet-500:focus { border-color: var(--brand-p500) !important; }
        @media print {
            /* Hide interactive elements */
            .fixed,
            .sticky,
            button,
            form,
            [x-show],
            [x-data] button {
                display: none !important;
            }

            /* Remove backgrounds for ink saving */
            .bg-amber-50,
            .bg-white {
                background: white !important;
            }

            /* Clean typography */
            body {
                font-size: 11pt;
                color: #000;
            }

            /* Ensure content fills page width */
            .max-w-2xl {
                max-width: 100% !important;
                padding: 0 !important;
            }

            /* Remove bottom padding added for sticky bar */
            .pb-32 {
                padding-bottom: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Brand header --}}
    <div class="text-white px-4 py-3" style="background-color: var(--brand-primary);">
        <div class="max-w-2xl mx-auto flex items-center gap-3">
            @php $headerLogo = $branding->logoUrl(); @endphp
            @if($headerLogo)
                <img src="{{ $headerLogo }}" alt="{{ $branding->appName() }}" class="h-6 w-auto object-contain brightness-0 invert">
            @endif
            <span class="font-semibold text-sm tracking-tight">{{ $branding->appName() }}</span>
            <span class="text-white/50 text-sm">&mdash; Pengesahan Minit</span>
        </div>
    </div>

    {{-- Sticky deadline banner --}}
    @if($circulation->isOpen())
        <div class="sticky top-0 z-20 bg-amber-50 border-b border-amber-200 px-4 py-3">
            <p class="text-sm font-medium text-amber-800 max-w-2xl mx-auto">
                ⏳ {{ __('mom.deadline_banner', ['deadline' => $circulation->deadline_at->format('d M Y, g:i A')]) }}
            </p>
            <p class="text-xs text-amber-600 mt-0.5 max-w-2xl mx-auto">
                {{ __('mom.deadline_warning') }}
            </p>
        </div>
    @else
        <div class="sticky top-0 z-20 bg-gray-100 border-b border-gray-300 px-4 py-3">
            <p class="text-sm font-medium text-gray-700 max-w-2xl mx-auto">
                🔒 {{ __('mom.circulation_closed') }}
            </p>
        </div>
    @endif

    {{-- Identity strip --}}
    <div class="bg-white border-b border-gray-100 px-4 py-2">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <span class="text-sm text-gray-600">
                Anda: <strong>{{ $recipient->name }}</strong> ({{ $recipient->email }})
            </span>
            <a href="#" class="text-xs text-violet-600 hover:underline">{{ __('mom.not_you') }}</a>
        </div>
    </div>

    {{-- Content area --}}
    <div class="max-w-2xl mx-auto px-4 py-6 pb-32">

        {{-- Meeting header --}}
        <div class="mb-6">
            @if($meeting->mom_number)
                <p class="text-xs text-gray-500 mb-1">{{ $meeting->mom_number }}</p>
            @endif
            <h1 class="text-xl font-bold text-gray-900">{{ $meeting->title }}</h1>
            <div class="flex flex-wrap gap-3 mt-1 text-sm text-gray-500">
                @if($meeting->meeting_date)
                    <span>{{ $meeting->meeting_date->format('d M Y') }}</span>
                @endif
                @if($meeting->start_time)
                    <span>{{ $meeting->start_time->format('H:i') }}{{ $meeting->end_time ? ' – ' . $meeting->end_time->format('H:i') : '' }}</span>
                @endif
                @if($meeting->location)
                    <span>{{ $meeting->location }}</span>
                @endif
            </div>
        </div>

        {{-- Summary --}}
        @if($meeting->summary)
            <section class="mb-6 bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Ringkasan</h2>
                <p class="text-sm text-gray-600 leading-relaxed">{{ $meeting->summary }}</p>
            </section>
        @endif

        {{-- Topics / Agenda --}}
        @if($meeting->topics->isNotEmpty())
            <section class="mb-6">
                <h2 class="text-base font-semibold text-gray-800 mb-3">Agenda / Perbincangan</h2>
                <div class="space-y-4">
                    @foreach($meeting->topics as $topic)
                        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100" x-data="{ remarkOpen: false }">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-sm font-medium text-gray-800">{{ $topic->title }}</h3>
                                @if($topic->duration_minutes)
                                    <span class="text-xs text-gray-400 shrink-0">({{ $topic->duration_minutes }} min)</span>
                                @endif
                            </div>
                            @if($topic->description)
                                <p class="text-sm text-gray-500 mt-1 leading-relaxed">{{ $topic->description }}</p>
                            @endif
                            <button @click="remarkOpen = !remarkOpen"
                                    class="mt-2 text-xs text-violet-600 hover:underline">
                                💬 Remark
                            </button>
                            <form method="POST" action="{{ route('mom.remark', $recipient->token) }}"
                                  x-show="remarkOpen" x-cloak class="mt-2">
                                @csrf
                                <input type="hidden" name="commentable_type" value="topic">
                                <input type="hidden" name="commentable_id" value="{{ $topic->id }}">
                                <textarea
                                    name="body"
                                    rows="3"
                                    placeholder="Tulis pembetulan atau ulasan..."
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:ring-violet-500 focus:border-violet-500"
                                    required
                                ></textarea>
                                <button type="submit"
                                        class="mt-1 text-xs bg-violet-600 text-white px-3 py-1 rounded hover:bg-violet-700">
                                    Hantar Remark
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Action Items --}}
        @if($meeting->actionItems->isNotEmpty())
            <section class="mb-6">
                <h2 class="text-base font-semibold text-gray-800 mb-3">Tindakan</h2>
                <div class="space-y-3">
                    @foreach($meeting->actionItems as $item)
                        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100" x-data="{ remarkOpen: false }">
                            <p class="text-sm font-medium text-gray-800">{{ $item->title }}</p>
                            <div class="flex flex-wrap gap-3 mt-1 text-xs text-gray-500">
                                @if($item->assignedTo?->name)
                                    <span>{{ $item->assignedTo->name }}</span>
                                @endif
                                @if($item->due_date)
                                    <span>{{ $item->due_date->format('d M Y') }}</span>
                                @endif
                                @if($item->status)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        @switch($item->status?->value)
                                            @case('open') bg-gray-100 text-gray-700 @break
                                            @case('in_progress') bg-blue-100 text-blue-700 @break
                                            @case('completed') bg-green-100 text-green-700 @break
                                            @case('cancelled') bg-red-100 text-red-700 @break
                                            @default bg-gray-100 text-gray-700
                                        @endswitch
                                    ">
                                        {{ ucwords(str_replace('_', ' ', $item->status?->value ?? 'open')) }}
                                    </span>
                                @endif
                            </div>
                            <button @click="remarkOpen = !remarkOpen"
                                    class="mt-2 text-xs text-violet-600 hover:underline">
                                💬 Remark
                            </button>
                            <form method="POST" action="{{ route('mom.remark', $recipient->token) }}"
                                  x-show="remarkOpen" x-cloak class="mt-2">
                                @csrf
                                <input type="hidden" name="commentable_type" value="action_item">
                                <input type="hidden" name="commentable_id" value="{{ $item->id }}">
                                <textarea
                                    name="body"
                                    rows="2"
                                    placeholder="Tulis ulasan..."
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:ring-violet-500 focus:border-violet-500"
                                    required
                                ></textarea>
                                <button type="submit"
                                        class="mt-1 text-xs bg-violet-600 text-white px-3 py-1 rounded hover:bg-violet-700">
                                    Hantar Remark
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Attendees --}}
        @if($meeting->attendees->isNotEmpty())
            <section class="mb-6 bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Peserta</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($meeting->attendees as $attendee)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 rounded-full text-sm text-gray-700">
                            <span class="w-5 h-5 rounded-full bg-violet-200 text-violet-700 text-xs font-medium flex items-center justify-center">
                                {{ strtoupper(substr($attendee->name ?? 'T', 0, 1)) }}
                            </span>
                            {{ $attendee->name ?? 'Tetamu' }}
                            @if($attendee->role)
                                <span class="text-xs text-gray-400">({{ $attendee->role }})</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Conversion CTA: shown only after confirmation --}}
        @if($recipient->isConfirmed())
        <div class="mt-4 p-4 bg-violet-50 rounded-lg border border-violet-100">
            <p class="text-sm font-medium text-violet-800">
                Ingin minit mesyuarat anda sendiri direkod dan diedarkan secara automatik?
            </p>
            <p class="text-xs text-violet-700 mt-1">
                antaraNote merekod, meringkaskan, dan mengedarkan minit untuk semua jenis mesyuarat.
            </p>
            <a href="{{ route('register') }}?name={{ urlencode($recipient->name) }}&email={{ urlencode($recipient->email) }}"
               class="mt-3 inline-block text-xs font-medium text-white bg-violet-600 hover:bg-violet-700 rounded px-3 py-1.5">
                Daftar percuma →
            </a>
            <p class="text-xs text-gray-400 mt-2">
                Maklumat anda tidak dikongsi tanpa kebenaran anda.
            </p>
        </div>
        @endif

        <x-antara-note-footer />
    </div>

    {{-- Sticky bottom action bar --}}
    @if(! $circulation->isOpen())
        <div class="fixed bottom-0 inset-x-0 bg-gray-100 border-t border-gray-300 px-4 py-4">
            <p class="text-center text-sm font-medium text-gray-600 max-w-2xl mx-auto">
                {{ __('mom.circulation_closed') }}
            </p>
        </div>
    @elseif($recipient->isConfirmed())
        <div class="fixed bottom-0 inset-x-0 bg-green-50 border-t border-green-200 px-4 py-4">
            <div class="max-w-2xl mx-auto">
                <p class="text-center text-sm font-medium text-green-700">
                    ✅ {{ __('mom.confirmed_at', ['time' => $recipient->responded_at?->format('d M Y, g:i A')]) }}
                </p>
                <p class="text-center text-xs text-green-600 mt-1">
                    <form method="POST" action="{{ route('mom.confirm.destroy', $recipient->token) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-green-600 underline">{{ __('mom.withdrawal_link') }}</button>
                    </form>
                </p>
            </div>
        </div>
    @else
        <div class="fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 px-4 py-4">
            <div class="max-w-2xl mx-auto flex gap-3">
                <form method="POST" action="{{ route('mom.confirm.store', $recipient->token) }}" class="flex-1">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-xl bg-violet-600 text-white text-sm font-semibold py-3 hover:bg-violet-700 active:scale-95 transition-all">
                        ✅ {{ __('mom.confirm_button') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('mom.amendments.store', $recipient->token) }}" class="flex-none">
                    @csrf
                    <button type="submit"
                            class="rounded-xl border border-gray-300 text-gray-700 text-sm font-medium px-4 py-3 hover:bg-gray-50 active:scale-95 transition-all">
                        💬 {{ __('mom.amendment_button') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

</body>
</html>
