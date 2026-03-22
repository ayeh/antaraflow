<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Comments</h2>

    @if($comments->isNotEmpty())
        <div class="space-y-4">
            @foreach($comments as $comment)
                <div class="border border-gray-200 dark:border-slate-700 rounded-lg p-4 bg-white dark:bg-slate-900" x-data="{ showReply: false }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">{{ strtoupper(substr($comment->user->name, 0, 1)) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $comment->user->name }}</span>
                                    <span class="text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{!! \Illuminate\Support\Str::of(e($comment->body))->replaceMatches('/@([\w-]+)/', '<span class="text-blue-600 dark:text-blue-400 font-medium">@$1</span>') !!}</p>

                                {{-- Reaction bar --}}
                                @php
                                    $reactionCounts = $comment->reactionCountsByEmoji();
                                    $userReactions = $comment->userReactionEmojis(auth()->id());
                                @endphp
                                <div class="flex items-center gap-1 mt-2 flex-wrap"
                                     x-data="{
                                         allReactions: @js($reactionCounts->map(fn($r) => ['emoji' => $r->emoji, 'count' => (int) $r->count])->toArray()),
                                         userReactions: @js($userReactions->toArray()),
                                         async toggleReaction(emoji) {
                                             const response = await fetch('{{ route('comments.reactions.toggle', $comment) }}', {
                                                 method: 'POST',
                                                 headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                                                 body: JSON.stringify({emoji})
                                             });
                                             const data = await response.json();
                                             if (data.action === 'added') {
                                                 if (!this.userReactions.includes(emoji)) this.userReactions.push(emoji);
                                                 this.allReactions[emoji] = {emoji, count: data.count};
                                             } else {
                                                 this.userReactions = this.userReactions.filter(e => e !== emoji);
                                                 if (data.count === 0) delete this.allReactions[emoji];
                                                 else this.allReactions[emoji] = {emoji, count: data.count};
                                             }
                                         },
                                         countFor(emoji) {
                                             return this.allReactions[emoji]?.count ?? 0;
                                         }
                                     }"
                                >
                                    @foreach(['👍', '❤️', '😂', '😮', '😢', '🎉'] as $emoji)
                                    <button
                                        @click="toggleReaction('{{ $emoji }}')"
                                        :class="userReactions.includes('{{ $emoji }}') ? 'bg-blue-100 dark:bg-blue-900 border-blue-400' : 'border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700'"
                                        class="text-xs px-1.5 py-0.5 rounded-full border transition-colors flex items-center gap-0.5"
                                    >
                                        <span>{{ $emoji }}</span>
                                        <span x-text="countFor('{{ $emoji }}')" x-show="countFor('{{ $emoji }}') > 0" class="text-slate-600 dark:text-slate-400"></span>
                                    </button>
                                    @endforeach
                                </div>

                                <div class="mt-2 flex items-center gap-3">
                                    <button @click="showReply = !showReply" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Reply</button>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span x-data="{ clientVisible: {{ $comment->client_visible ? 'true' : 'false' }} }">
                                <button
                                    type="button"
                                    @click="fetch('{{ route('comments.toggle-visibility', $comment) }}', {method:'PATCH', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}).then(r=>r.json()).then(d=>{ clientVisible = d.client_visible })"
                                    :title="clientVisible ? 'Visible to clients' : 'Internal only'"
                                    :class="clientVisible ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-slate-500'"
                                    class="p-1 rounded hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              :d="clientVisible
                                                ? 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'
                                                : 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21'" />
                                    </svg>
                                </button>
                            </span>
                            @if(auth()->id() === $comment->user_id)
                                <form method="POST" action="{{ route('comments.destroy', $comment) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 dark:text-red-400 hover:underline" onclick="return confirm('Delete this comment?')">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- Replies --}}
                    @if($comment->replies->isNotEmpty())
                        <div class="mt-4 ml-11 space-y-3">
                            @foreach($comment->replies as $reply)
                                <div class="border-l-2 border-gray-200 dark:border-slate-600 pl-4 flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-3 flex-1 min-w-0">
                                        <div class="w-7 h-7 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ strtoupper(substr($reply->user->name, 0, 1)) }}</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $reply->user->name }}</span>
                                                <span class="text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</span>
                                            </div>
                                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $reply->body }}</p>
                                        </div>
                                    </div>
                                    @if(auth()->id() === $reply->user_id)
                                        <form method="POST" action="{{ route('comments.destroy', $reply) }}" class="flex-shrink-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-500 dark:text-red-400 hover:underline" onclick="return confirm('Delete this reply?')">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Reply form --}}
                    <div x-show="showReply" x-cloak class="mt-4 ml-11">
                        <form method="POST" action="{{ route('meetings.comments.store', $meeting) }}" class="flex gap-2">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                            <input type="text" name="body" placeholder="Write a reply..." required maxlength="2000"
                                class="flex-1 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">Reply</button>
                            <button type="button" @click="showReply = false" class="px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">No comments yet. Be the first to comment.</p>
    @endif

    <div class="border border-gray-200 dark:border-slate-700 rounded-lg p-4 bg-gray-50 dark:bg-slate-800">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Add a Comment</h3>
        <form method="POST" action="{{ route('meetings.comments.store', $meeting) }}" class="space-y-3">
            @csrf
            <textarea name="body" rows="3" required maxlength="2000" placeholder="Write your comment..."
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"></textarea>
            <div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">Post Comment</button>
            </div>
        </form>
    </div>
</div>
