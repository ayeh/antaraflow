<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\BoardSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BoardSetting> */
class BoardSettingFactory extends Factory
{
    protected $model = BoardSetting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'quorum_type' => 'percentage',
            'quorum_value' => 50,
            'require_chair' => false,
            'require_secretary' => false,
            'voting_enabled' => true,
            'chair_casting_vote' => false,
            'block_finalization_without_quorum' => false,
        ];
    }

    public function withQuorumBlocking(): static
    {
        return $this->state([
            'block_finalization_without_quorum' => true,
        ]);
    }

    public function withChairCastingVote(): static
    {
        return $this->state([
            'chair_casting_vote' => true,
        ]);
    }

    public function countBased(int $count = 3): static
    {
        return $this->state([
            'quorum_type' => 'count',
            'quorum_value' => $count,
        ]);
    }
}
