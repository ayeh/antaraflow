<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->create([
            'name' => 'AntaraFlow Demo',
            'slug' => 'antaraflow-demo',
            'description' => 'Demo organization for AntaraFlow.',
            'timezone' => 'UTC',
            'language' => 'en',
        ]);

        $owner = User::query()->create([
            'name' => 'Demo Owner',
            'email' => 'demo@antaraflow.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'current_organization_id' => $org->id,
        ]);
        $org->members()->attach($owner, ['role' => UserRole::Owner->value]);

        $adminEmails = ['admin1@antaraflow.test', 'admin2@antaraflow.test'];
        foreach ($adminEmails as $email) {
            $admin = User::factory()->create([
                'email' => $email,
                'current_organization_id' => $org->id,
            ]);
            $org->members()->attach($admin, ['role' => UserRole::Admin->value]);
        }

        $memberEmails = ['member1@antaraflow.test', 'member2@antaraflow.test', 'member3@antaraflow.test'];
        foreach ($memberEmails as $email) {
            $member = User::factory()->create([
                'email' => $email,
                'current_organization_id' => $org->id,
            ]);
            $org->members()->attach($member, ['role' => UserRole::Member->value]);
        }

        $viewer = User::factory()->create([
            'email' => 'viewer@antaraflow.test',
            'current_organization_id' => $org->id,
        ]);
        $org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->first();
        if ($proPlan) {
            OrganizationSubscription::query()->create([
                'organization_id' => $org->id,
                'subscription_plan_id' => $proPlan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
            ]);
        }

        AiProviderConfig::query()->create([
            'organization_id' => $org->id,
            'provider' => 'openai',
            'display_name' => 'OpenAI (Default)',
            'api_key_encrypted' => encrypt('sk-placeholder-key'),
            'model' => 'gpt-4o',
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
