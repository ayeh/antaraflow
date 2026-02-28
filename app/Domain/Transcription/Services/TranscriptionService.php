<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Jobs\ProcessTranscriptionJob;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Models\User;
use App\Support\Enums\InputType;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class TranscriptionService
{
    public function __construct(
        private readonly AudioStorageService $storage,
    ) {}

    public function upload(UploadedFile $file, MinutesOfMeeting $mom, User $user, string $language = 'en'): AudioTranscription
    {
        $path = $this->storage->store($file, $mom->organization_id);

        $transcription = AudioTranscription::query()->create([
            'minutes_of_meeting_id' => $mom->id,
            'uploaded_by' => $user->id,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'audio/mpeg',
            'file_size' => $file->getSize(),
            'language' => $language,
            'status' => TranscriptionStatus::Pending,
        ]);

        $mom->inputs()->create([
            'type' => InputType::Audio,
            'source_type' => AudioTranscription::class,
            'source_id' => $transcription->id,
        ]);

        ProcessTranscriptionJob::dispatch($transcription);

        return $transcription;
    }

    public function getSegments(AudioTranscription $transcription): Collection
    {
        return $transcription->segments()->orderBy('sequence_order')->get();
    }

    public function updateSegment(TranscriptionSegment $segment, string $text): TranscriptionSegment
    {
        $segment->update([
            'text' => $text,
            'is_edited' => true,
        ]);

        return $segment->fresh();
    }
}
