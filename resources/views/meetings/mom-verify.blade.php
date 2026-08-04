<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengesahan Dokumen — antaraNote</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-xl shadow-sm border border-gray-200 p-6">

        {{-- Brand header --}}
        <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 68 50" width="18" height="13" aria-hidden="true">
                <rect x="0"  y="21" width="7" height="16" rx="3.5" fill="#095153"/>
                <rect x="11" y="10" width="7" height="36" rx="3.5" fill="#095153"/>
                <rect x="22" y="16" width="7" height="25" rx="3.5" fill="#095153"/>
                <rect x="33" y="4"  width="7" height="50" rx="3.5" fill="#095153"/>
                <rect x="44" y="13" width="7" height="31" rx="3.5" fill="#095153"/>
                <rect x="55" y="8"  width="7" height="43" rx="3.5" fill="#095153"/>
                <rect x="66" y="19" width="7" height="22" rx="3.5" fill="#095153"/>
            </svg>
            <span class="text-sm font-medium text-gray-700">
                <span class="font-normal">antara</span><span class="font-bold">Note</span>
            </span>
            <span class="text-gray-300 text-sm ml-auto text-xs">Pengesahan Dokumen</span>
        </div>

        {{-- Status badge --}}
        <div class="text-center mb-6">
            @if($meeting->status === \App\Support\Enums\MeetingStatus::Approved)
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-green-100 mb-3">
                    <svg class="w-7 h-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h1 class="text-lg font-bold text-gray-900">Minit Disahkan</h1>
                <p class="text-sm text-green-600 mt-1">Dokumen ini telah disahkan secara rasmi.</p>
            @else
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-gray-100 mb-3">
                    <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-lg font-bold text-gray-900">Status Tidak Disahkan</h1>
                <p class="text-sm text-gray-500 mt-1">Dokumen ini belum mendapat pengesahan rasmi.</p>
            @endif
        </div>

        {{-- Integrity facts --}}
        <dl class="space-y-3 text-sm divide-y divide-gray-50">
            <div class="flex justify-between py-2">
                <dt class="text-gray-500">Tajuk</dt>
                <dd class="text-gray-900 font-medium text-right max-w-xs">{{ $meeting->title }}</dd>
            </div>

            @if($meeting->mom_number)
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">Nombor Minit</dt>
                    <dd class="text-gray-900 font-mono text-sm">{{ $meeting->mom_number }}</dd>
                </div>
            @endif

            <div class="flex justify-between py-2">
                <dt class="text-gray-500">Tarikh Mesyuarat</dt>
                <dd class="text-gray-900">{{ $meeting->meeting_date?->format('d M Y') ?? '-' }}</dd>
            </div>

            @if($circulation)
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">Diedarkan</dt>
                    <dd class="text-gray-900">{{ $circulation->created_at->format('d M Y') }} (Pusingan {{ $circulation->round }})</dd>
                </div>

                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">Pengesahan</dt>
                    <dd class="text-gray-900">{{ $confirmedCount }} / {{ $totalCount }} penerima</dd>
                </div>

                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">Ditutup</dt>
                    <dd class="text-gray-900">{{ $circulation->closed_at?->format('d M Y, g:i A') ?? '-' }}</dd>
                </div>
            @endif
        </dl>

        <p class="mt-6 text-xs text-gray-400 text-center">
            Halaman ini mengesahkan integriti dokumen sahaja. Kandungan mesyuarat tidak didedahkan.
        </p>

    </div>

</body>
</html>
