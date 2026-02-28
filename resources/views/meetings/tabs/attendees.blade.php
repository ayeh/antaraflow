<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900">Attendees</h3>
    </div>

    @php
        $attendees = $meeting->attendees()->with('user')->get();
        $groups = \App\Domain\Attendee\Models\AttendeeGroup::query()->get();
    @endphp

    <div x-data="{ showAddForm: false }" class="space-y-4">
        <div class="flex items-center gap-3">
            <button @click="showAddForm = !showAddForm" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">Add Attendee</button>

            @if($groups->isNotEmpty())
                <form method="POST" action="{{ route('meetings.attendees.bulk-invite', $meeting) }}" class="flex items-center gap-2">
                    @csrf
                    <select name="group_id" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">Bulk Invite</button>
                </form>
            @endif
        </div>

        <div x-show="showAddForm" x-transition class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <form method="POST" action="{{ route('meetings.attendees.store', $meeting) }}">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="attendee_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" id="attendee_name" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label for="attendee_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="attendee_email" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label for="attendee_role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="role" id="attendee_role" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            @foreach(\App\Support\Enums\AttendeeRole::cases() as $role)
                                <option value="{{ $role->value }}">{{ ucfirst(str_replace('_', ' ', $role->value)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="attendee_dept" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <input type="text" name="department" id="attendee_dept" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_external" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        External attendee
                    </label>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">Add</button>
                </div>
            </form>
        </div>
    </div>

    @if($attendees->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">RSVP</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                        <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($attendees as $attendee)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $attendee->name }}
                                @if($attendee->is_external)
                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">External</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $attendee->email ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">{{ ucfirst(str_replace('_', ' ', $attendee->role->value)) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('meetings.attendees.rsvp', [$meeting, $attendee]) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <select name="rsvp_status" onchange="this.form.submit()" class="rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                        @foreach(\App\Support\Enums\RsvpStatus::cases() as $status)
                                            <option value="{{ $status->value }}" {{ $attendee->rsvp_status === $status ? 'selected' : '' }}>{{ ucfirst($status->value) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('meetings.attendees.presence', [$meeting, $attendee]) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_present" value="{{ $attendee->is_present ? '0' : '1' }}">
                                    <button type="submit" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $attendee->is_present ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $attendee->is_present ? 'Present' : 'Absent' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('meetings.attendees.destroy', [$meeting, $attendee]) }}" class="inline" onsubmit="return confirm('Remove this attendee?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-700 text-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500 text-center py-8">No attendees added yet.</p>
    @endif
</div>
