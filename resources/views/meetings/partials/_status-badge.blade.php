{{-- Usage: @include('meetings.partials._status-badge', ['status' => $meeting->status]) --}}
@php
$value = $status instanceof \App\Support\Enums\MeetingStatus ? $status->value : $status;
$config = match($value) {
    'draft'       => ['label' => 'Draft',       'class' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300', 'pulse' => false],
    'in_progress' => ['label' => 'In Progress', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',  'pulse' => true],
    'finalized'   => ['label' => 'Finalized',   'class' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', 'pulse' => false],
    'approved'    => ['label' => 'Approved',     'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'pulse' => false],
    default       => ['label' => ucfirst(str_replace('_', ' ', $value)), 'class' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300', 'pulse' => false],
};
@endphp
<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $config['class'] }}">
    @if($config['pulse'])
        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
    @endif
    {{ $config['label'] }}
</span>
