@if($circulation)
<div class="mt-6 mb-8 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">
            {{ __('Circulation Monitor') }} · {{ __('Round') }} {{ $circulation->round }}
        </h3>
        <span class="text-xs text-gray-500 dark:text-slate-400">
            {{ __('Deadline') }}: {{ $circulation->deadline_at->format('d M Y, g:i A') }}
        </span>
    </div>

    {{-- Tally bar --}}
    @php
        $total     = $circulation->recipients->count();
        $confirmed = $circulation->recipients->where('response', 'confirmed')->count();
        $amended   = $circulation->recipients->where('response', 'amendment_requested')->count();
        $notOpened = $circulation->recipients->filter(fn ($r) => ($r->open_count ?? 0) === 0)->count();
        $pending   = $total - $confirmed - $amended;
    @endphp

    <div class="px-4 py-3 flex flex-wrap gap-4 text-sm border-b border-gray-100 dark:border-slate-700">
        <span class="text-green-600 dark:text-green-400 font-medium">
            ✓ {{ $confirmed }} {{ __('confirmed') }}
        </span>
        <span class="text-amber-600 dark:text-amber-400 font-medium">
            💬 {{ $amended }} {{ __('amendment') }}
        </span>
        <span class="text-gray-400 dark:text-slate-500">
            ○ {{ $pending }} {{ __('no response') }}
        </span>
        @if($notOpened > 0)
            <span class="text-red-500 dark:text-red-400 font-medium">
                ✕ {{ $notOpened }} {{ __('not opened') }}
            </span>
        @endif
    </div>

    {{-- Recipient list --}}
    <ul class="divide-y divide-gray-100 dark:divide-slate-700">

        {{-- Not opened first (highest urgency) --}}
        @foreach($circulation->recipients->filter(fn ($r) => ($r->open_count ?? 0) === 0) as $recipient)
            <li class="px-4 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $recipient->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-slate-500">{{ $recipient->email }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-red-500 dark:text-red-400 font-medium">{{ __('Not opened') }}</span>
                    @if($circulation->isOpen() && ! $recipient->hasResponded())
                        <form method="POST" action="{{ route('circulation.recipients.destroy', [$circulation, $recipient]) }}"
                              onsubmit="confirmThenSubmit(event, @js(__('Remove :name from this circulation?', ['name' => $recipient->name])), { title: @js(__('mom.remove_recipient')), confirmLabel: @js(__('mom.remove_recipient')), type: 'danger' })">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 dark:text-red-500 dark:hover:text-red-400">
                                {{ __('mom.remove_recipient') }}
                            </button>
                        </form>
                    @endif
                </div>
            </li>
        @endforeach

        {{-- Responded: confirmed or amendment --}}
        @foreach($circulation->recipients->filter(fn ($r) => $r->response !== null) as $recipient)
            <li class="px-4 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $recipient->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-slate-500">
                        {{ $recipient->responded_at?->format('d M Y, g:i A') }}
                    </p>
                </div>
                <span @class([
                    'text-xs font-medium px-2 py-0.5 rounded-full',
                    'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' => $recipient->response === 'confirmed',
                    'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300' => $recipient->response === 'amendment_requested',
                ])>
                    {{ $recipient->response === 'confirmed' ? '✓ ' . __('Confirmed') : '💬 ' . __('Amendment') }}
                </span>
            </li>
        @endforeach

        {{-- Opened but no response yet --}}
        @foreach($circulation->recipients->filter(fn ($r) => ($r->open_count ?? 0) > 0 && $r->response === null) as $recipient)
            <li class="px-4 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $recipient->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-slate-500">{{ __('Opened, awaiting response') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 dark:text-slate-400">○</span>
                    @if($circulation->isOpen())
                        <form method="POST" action="{{ route('circulation.recipients.destroy', [$circulation, $recipient]) }}"
                              onsubmit="confirmThenSubmit(event, @js(__('Remove :name from this circulation?', ['name' => $recipient->name])), { title: @js(__('mom.remove_recipient')), confirmLabel: @js(__('mom.remove_recipient')), type: 'danger' })">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 dark:text-red-500 dark:hover:text-red-400">
                                {{ __('mom.remove_recipient') }}
                            </button>
                        </form>
                    @endif
                </div>
            </li>
        @endforeach

    </ul>

    {{-- Add recipient (only when circulation is open) --}}
    @if($circulation->isOpen())
    <div class="border-t border-gray-100 dark:border-slate-700 px-4 py-3" x-data="{ open: false }">
        <button type="button" @click="open = !open"
                class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
            + {{ __('mom.add_recipient') }}
        </button>

        <form x-show="open" x-cloak method="POST"
              action="{{ route('circulation.recipients.store', $circulation) }}"
              class="mt-3 flex flex-col sm:flex-row gap-2">
            @csrf
            <input type="text" name="name" placeholder="{{ __('mom.recipient_name_placeholder') }}" required
                   class="flex-1 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm px-3 py-1.5 outline-none focus:ring-1 focus:ring-indigo-500 dark:text-slate-100">
            <input type="email" name="email" placeholder="{{ __('mom.recipient_email_placeholder') }}" required
                   class="flex-1 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm px-3 py-1.5 outline-none focus:ring-1 focus:ring-indigo-500 dark:text-slate-100">
            <button type="submit"
                    class="px-4 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 whitespace-nowrap">
                {{ __('mom.add_recipient') }}
            </button>
            <button type="button" @click="open = false"
                    class="px-3 py-1.5 text-xs text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200">
                {{ __('Cancel') }}
            </button>
        </form>
    </div>
    @endif

    {{-- Amendment queue: pending remarks from guests --}}
    @php
        $pendingRemarks = \App\Domain\Collaboration\Models\Comment::withoutGlobalScopes()
            ->whereHas('circulationRecipient', fn ($q) => $q->where('mom_circulation_id', $circulation->id))
            ->whereNull('resolved_at')
            ->with('circulationRecipient')
            ->get();
    @endphp

    @if($pendingRemarks->isNotEmpty())
    <div class="border-t border-gray-100 dark:border-slate-700 px-4 py-3">
        <h4 class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-3">
            {{ __('Pending Remarks') }}
        </h4>
        @foreach($pendingRemarks as $remark)
        <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-100 dark:border-amber-800">
            <p class="text-xs text-gray-500 dark:text-slate-400">
                {{ $remark->circulationRecipient?->name }} · {{ $remark->created_at->format('d M, g:i A') }}
            </p>
            <p class="text-sm text-gray-800 dark:text-slate-200 mt-1">{{ $remark->body }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('meetings.amendment.decide', [$meeting, $remark]) }}">
                    @csrf
                    <input type="hidden" name="decision" value="minor">
                    <button type="submit" class="text-xs px-2 py-1 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 rounded hover:bg-blue-200 dark:hover:bg-blue-900/60">
                        {{ __('Minor correction') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('meetings.amendment.decide', [$meeting, $remark]) }}">
                    @csrf
                    <input type="hidden" name="decision" value="material">
                    <button type="submit" class="text-xs px-2 py-1 bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 rounded hover:bg-orange-200 dark:hover:bg-orange-900/60">
                        {{ __('Material amendment') }} → {{ __('Round') }} {{ $circulation->round + 1 }}
                    </button>
                </form>
                <form method="POST" action="{{ route('meetings.amendment.decide', [$meeting, $remark]) }}">
                    @csrf
                    <input type="hidden" name="decision" value="reject">
                    <button type="submit" class="text-xs px-2 py-1 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 rounded hover:bg-gray-200 dark:hover:bg-slate-600">
                        {{ __('Reject') }}
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
@endif
