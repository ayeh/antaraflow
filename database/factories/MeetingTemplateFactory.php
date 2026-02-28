<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MeetingTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeetingTemplate> */
class MeetingTemplateFactory extends Factory
{
    protected $model = MeetingTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'name' => fake()->words(3, true).' Template',
            'description' => fake()->sentence(),
            'structure' => [
                'sections' => [
                    ['title' => 'Attendees', 'type' => 'attendees'],
                    ['title' => 'Agenda', 'type' => 'text'],
                    ['title' => 'Discussion', 'type' => 'text'],
                    ['title' => 'Action Items', 'type' => 'action_items'],
                ],
            ],
            'default_settings' => null,
            'is_default' => false,
            'is_shared' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function private(): static
    {
        return $this->state(['is_shared' => false]);
    }
}
