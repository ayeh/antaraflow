<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Admin\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'welcome',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to {{app_name}}, {{user_name}}!',
                'body_html' => '<h1>Welcome, {{user_name}}!</h1><p>Thank you for joining {{app_name}}. Your organization <strong>{{org_name}}</strong> is ready to go.</p>',
                'variables' => ['user_name', 'app_name', 'org_name', 'login_url'],
            ],
            [
                'slug' => 'password-reset',
                'name' => 'Password Reset',
                'subject' => 'Reset Your Password — {{app_name}}',
                'body_html' => '<h1>Password Reset</h1><p>Hi {{user_name}}, click the link below to reset your password:</p><p><a href="{{reset_url}}">Reset Password</a></p>',
                'variables' => ['user_name', 'app_name', 'reset_url'],
            ],
            [
                'slug' => 'meeting-invite',
                'name' => 'Meeting Invitation',
                'subject' => 'You are invited to: {{meeting_title}}',
                'body_html' => '<h1>Meeting Invitation</h1><p>Hi {{user_name}}, you have been invited to <strong>{{meeting_title}}</strong> on {{meeting_date}}.</p>',
                'variables' => ['user_name', 'meeting_title', 'meeting_date', 'meeting_url', 'org_name'],
            ],
            [
                'slug' => 'action-item-reminder',
                'name' => 'Action Item Reminder',
                'subject' => 'Reminder: {{action_title}} is due {{due_date}}',
                'body_html' => '<h1>Action Item Reminder</h1><p>Hi {{user_name}}, your action item <strong>{{action_title}}</strong> is due on {{due_date}}.</p>',
                'variables' => ['user_name', 'action_title', 'due_date', 'meeting_title'],
            ],
            [
                'slug' => 'meeting-finalized',
                'name' => 'Meeting Finalized',
                'subject' => 'Meeting Minutes Finalized: {{meeting_title}}',
                'body_html' => '<h1>Minutes Finalized</h1><p>Hi {{user_name}}, the minutes for <strong>{{meeting_title}}</strong> have been finalized. Please review and take action on your assigned items.</p>',
                'variables' => ['user_name', 'meeting_title', 'meeting_url', 'org_name'],
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::query()->firstOrCreate(
                ['slug' => $template['slug']],
                $template,
            );
        }
    }
}
