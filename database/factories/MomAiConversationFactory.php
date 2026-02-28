<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\AI\Models\MomAiConversation;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MomAiConversation> */
class MomAiConversationFactory extends Factory
{
    protected $model = MomAiConversation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['user', 'assistant']),
            'message' => fake()->paragraph(),
            'context' => null,
            'token_usage' => null,
            'provider' => null,
        ];
    }

    public function userRole(): static
    {
        return $this->state(['role' => 'user']);
    }

    public function assistantRole(): static
    {
        return $this->state([
            'role' => 'assistant',
            'provider' => fake()->randomElement(['openai', 'anthropic', 'google']),
        ]);
    }
}
