<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $meeting->title }} — Pengesahan Minit</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Brand header --}}
    <div class="text-white px-4 py-3" style="background-color:#095153;">
        <div class="max-w-2xl mx-auto flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 68 50" width="22" height="16" aria-hidden="true">
                <rect x="0"  y="21" width="7" height="16" rx="3.5" fill="rgba(255,255,255,0.85)"/>
                <rect x="11" y="10" width="7" height="36" rx="3.5" fill="rgba(255,255,255,0.85)"/>
                <rect x="22" y="16" width="7" height="25" rx="3.5" fill="rgba(255,255,255,0.85)"/>
                <rect x="33" y="4"  width="7" height="50" rx="3.5" fill="rgba(255,255,255,0.85)"/>
                <rect x="44" y="13" width="7" height="31" rx="3.5" fill="rgba(255,255,255,0.85)"/>
                <rect x="55" y="8"  width="7" height="43" rx="3.5" fill="rgba(255,255,255,0.85)"/>
                <rect x="66" y="19" width="7" height="22" rx="3.5" fill="rgba(255,255,255,0.85)"/>
            </svg>
            <span class="font-semibold text-sm tracking-tight">
                <span style="font-weight:400;">antara</span><span style="font-weight:700;">Note</span>
            </span>
            <span class="text-white/50 text-sm">&mdash; Pengesahan Minit</span>
        </div>
    </div>

    {{-- Sticky deadline banner --}}
    <div class="sticky top-0 z-20 bg-amber-50 border-b border-amber-200 px-4 py-3">
        <p class="text-sm font-medium text-amber-800 max-w-2xl mx-auto">
            ⏳ Sila sahkan sebelum {{ $circulation->deadline_at->format('d M Y, g:i A') }}
        </p>
        <p class="text-xs text-amber-600 mt-0.5 max-w-2xl mx-auto">
            Tiada maklum balas akan dianggap sebagai pengesahan.
        </p>
    </div>

    {{-- Identity strip --}}
    <div class="bg-white border-b border-gray-100 px-4 py-2">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <span class="text-sm text-gray-600">
                Anda: <strong>{{ $recipient->name }}</strong> ({{ $recipient->email }})
            </span>
            <a href="#" class="text-xs text-blue-600 hover:underline">Bukan anda?</a>
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
                                    class="mt-2 text-xs text-blue-600 hover:underline">
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
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    required
                                ></textarea>
                                <button type="submit"
                                        class="mt-1 text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
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
                                    class="mt-2 text-xs text-blue-600 hover:underline">
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
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    required
                                ></textarea>
                                <button type="submit"
                                        class="mt-1 text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
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

        <x-antara-note-footer />
    </div>

    {{-- Sticky bottom action bar --}}
    @if($recipient->isConfirmed())
        <div class="fixed bottom-0 inset-x-0 bg-green-50 border-t border-green-200 px-4 py-4">
            <div class="max-w-2xl mx-auto">
                <p class="text-center text-sm font-medium text-green-700">
                    ✅ Anda telah mengesahkan minit ini pada {{ $recipient->responded_at?->format('d M Y, g:i A') }}
                </p>
                <p class="text-center text-xs text-green-600 mt-1">
                    <form method="POST" action="{{ route('mom.confirm.destroy', $recipient->token) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-green-600 underline">Tarik balik pengesahan</button>
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
                            class="w-full rounded-xl bg-blue-600 text-white text-sm font-semibold py-3 hover:bg-blue-700 active:scale-95 transition-all">
                        ✅ Sahkan Minit
                    </button>
                </form>
                <button type="button"
                        class="flex-none rounded-xl border border-gray-300 text-gray-700 text-sm font-medium px-4 py-3 hover:bg-gray-50 active:scale-95 transition-all">
                    💬 Pindaan
                </button>
            </div>
        </div>
    @endif

</body>
</html>
