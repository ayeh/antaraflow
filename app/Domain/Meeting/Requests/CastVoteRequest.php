<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use App\Support\Enums\VoteChoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CastVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'attendee_id' => ['required', 'exists:mom_attendees,id'],
            'vote' => ['required', Rule::enum(VoteChoice::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attendee_id.required' => __('The attendee is required.'),
            'attendee_id.exists' => __('The selected attendee is not valid.'),
            'vote.required' => __('A vote choice is required.'),
            'vote.enum' => __('The selected vote choice is invalid.'),
        ];
    }
}
