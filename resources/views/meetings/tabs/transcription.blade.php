<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900">Transcriptions</h3>
    </div>

    <form method="POST" action="{{ route('meetings.transcriptions.store', $meeting) }}" enctype="multipart/form-data" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
        @csrf
        <div class="space-y-3">
            <div class="flex items-end gap-4">
                <div class="flex-1">
                    <label for="audio_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upload Audio File</label>
                    <input type="file" name="audio" id="audio_file" accept="audio/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors whitespace-nowrap">Upload</button>
            </div>
            <x-language-select name="language" :selected="old('language', 'en')" />
        </div>
    </form>

    @php
        $transcriptions = $meeting->transcriptions()->latest()->get();
    @endphp

    @if($transcriptions->isNotEmpty())
        <div class="divide-y divide-gray-200">
            @foreach($transcriptions as $transcription)
                <div class="py-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-gray-900">{{ $transcription->original_filename ?? 'Transcription' }}</div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($transcription->status === 'completed') bg-green-100 text-green-700
                            @elseif($transcription->status === 'processing') bg-blue-100 text-blue-700
                            @elseif($transcription->status === 'failed') bg-red-100 text-red-700
                            @else bg-gray-100 text-gray-700
                            @endif">
                            {{ ucfirst($transcription->status ?? 'pending') }}
                        </span>
                    </div>
                    @if($transcription->segments && $transcription->segments->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            @foreach($transcription->segments as $segment)
                                <div class="bg-gray-50 rounded-lg p-3 text-sm">
                                    @if($segment->speaker_label)
                                        <span class="font-medium text-blue-600">{{ $segment->speaker_label }}:</span>
                                    @endif
                                    <span class="text-gray-700">{{ $segment->text }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 text-center py-8">No transcriptions yet. Upload an audio file to get started.</p>
    @endif
</div>
