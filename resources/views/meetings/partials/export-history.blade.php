@if($meeting->exports->isNotEmpty())
<div class="pt-4 border-t border-gray-200 dark:border-slate-700">
    <h4 class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Export History
    </h4>
    <div class="space-y-2">
        @foreach($meeting->exports as $export)
        <div class="flex items-center justify-between text-sm">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                    {{ strtoupper($export->format) === 'PDF'
                        ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                        : (strtoupper($export->format) === 'DOCX'
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                            : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400') }}">
                    {{ strtoupper($export->format) }}
                </span>
                <span class="text-gray-600 dark:text-slate-400 text-xs">{{ $export->user->name ?? 'Unknown' }}</span>
            </div>
            <span class="text-gray-400 dark:text-slate-500 text-xs">{{ $export->created_at->diffForHumans() }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif
