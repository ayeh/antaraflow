<div class="space-y-6" x-data="{ isLinkShare: false }">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Sharing</h2>

    @if($shares->isNotEmpty())
        <div class="overflow-hidden border border-gray-200 dark:border-slate-700 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                <thead class="bg-gray-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Shared With</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Permission</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expires</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Shared By</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
                    @foreach($shares as $share)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                @if($share->sharedWith)
                                    {{ $share->sharedWith->name }}
                                @else
                                    <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                        Link
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @if($share->permission === \App\Support\Enums\SharePermission::View) bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300
                                    @elseif($share->permission === \App\Support\Enums\SharePermission::Comment) bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300
                                    @elseif($share->permission === \App\Support\Enums\SharePermission::Edit) bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300
                                    @endif">
                                    {{ ucfirst($share->permission->value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                {{ $share->expires_at ? $share->expires_at->format('M j, Y') : 'Never' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                {{ $share->sharedBy?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('meetings.shares.destroy', [$meeting, $share]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline" onclick="return confirm('Revoke this share?')">Revoke</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">This meeting has not been shared yet.</p>
    @endif

    <div class="border border-gray-200 dark:border-slate-700 rounded-lg p-4 bg-gray-50 dark:bg-slate-800">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Add New Share</h3>
        <form method="POST" action="{{ route('meetings.shares.store', $meeting) }}" class="space-y-4">
            @csrf

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_link_share" name="is_link_share" value="1" x-model="isLinkShare" class="rounded border-gray-300 dark:border-slate-600 text-blue-600">
                <label for="is_link_share" class="text-sm text-gray-700 dark:text-gray-300">Generate shareable link (no specific user)</label>
            </div>

            <div x-show="!isLinkShare" x-cloak>
                <label for="shared_with_user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Share With User</label>
                <select id="shared_with_user_id" name="shared_with_user_id" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Select a member...</option>
                    @foreach($orgMembers as $member)
                        <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="permission" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Permission</label>
                <select id="permission" name="permission" required class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="view">View</option>
                    <option value="comment">Comment</option>
                    <option value="edit">Edit</option>
                </select>
            </div>

            <div>
                <label for="expires_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expiry Date <span class="text-gray-400">(optional)</span></label>
                <input type="date" id="expires_at" name="expires_at" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">Share</button>
            </div>
        </form>
    </div>
</div>
