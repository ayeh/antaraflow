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
use Illuminate\Support\Facades\Storage;

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

    public function createFromBrowserRecording(
        string $filePath,
        MinutesOfMeeting $mom,
        User $user,
        string $mimeType,
        int $durationSeconds,
        string $language = 'en',
    ): AudioTranscription {
        $disk = Storage::disk('local');
        $fileSize = $disk->size($filePath);
        $filename = basename($filePath);

        $transcription = AudioTranscription::query()->create([
            'minutes_of_meeting_id' => $mom->id,
            'uploaded_by' => $user->id,
            'original_filename' => $filename,
            'file_path' => $filePath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'duration_seconds' => $durationSeconds,
            'language' => $language,
            'status' => TranscriptionStatus::Pending,
        ]);

        $mom->inputs()->create([
            'type' => InputType::BrowserRecording,
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
