<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Account\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Get started with basic meeting management.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'features' => [
                    'transcription' => true,
                    'ai_summaries' => false,
                    'export' => false,
                ],
                'max_users' => 1,
                'max_meetings_per_month' => 5,
                'max_audio_minutes_per_month' => 30,
                'max_storage_mb' => 100,
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'For professionals who need more power.',
                'price_monthly' => 19.00,
                'price_yearly' => 190.00,
                'features' => [
                    'transcription' => true,
                    'ai_summaries' => true,
                    'export' => true,
                ],
                'max_users' => 5,
                'max_meetings_per_month' => 50,
                'max_audio_minutes_per_month' => 300,
                'max_storage_mb' => 5000,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'For teams that need advanced collaboration.',
                'price_monthly' => 49.00,
                'price_yearly' => 490.00,
                'features' => [
                    'transcription' => true,
                    'ai_summaries' => true,
                    'export' => true,
                    'custom_templates' => true,
                    'api_access' => true,
                ],
                'max_users' => 25,
                'max_meetings_per_month' => -1,
                'max_audio_minutes_per_month' => 1000,
                'max_storage_mb' => 25000,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Custom solutions for large organizations.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'features' => [
                    'transcription' => true,
                    'ai_summaries' => true,
                    'export' => true,
                    'custom_templates' => true,
                    'api_access' => true,
                    'sso' => true,
                    'dedicated_support' => true,
                ],
                'max_users' => -1,
                'max_meetings_per_month' => -1,
                'max_audio_minutes_per_month' => -1,
                'max_storage_mb' => -1,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan,
            );
        }
    }
}
