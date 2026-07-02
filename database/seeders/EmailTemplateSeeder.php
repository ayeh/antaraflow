<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Admin\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            EmailTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                $template,
            );
        }
    }

    /**
     * Wrap inner HTML in the shared, branded email shell so every template
     * carries a consistent header, footer, and button styling.
     */
    private function shell(string $heading, string $inner): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { border-bottom: 2px solid #7c3aed; padding-bottom: 16px; margin-bottom: 24px; }
                .header h2 { color: #7c3aed; margin: 0; font-size: 20px; }
                .body-content { margin-bottom: 24px; }
                .body-content p { margin: 0 0 12px; }
                .body-content ul { margin: 0 0 12px; padding-left: 20px; }
                .info { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin: 16px 0; }
                .info p { margin: 4px 0; font-size: 14px; }
                .info strong { color: #374151; }
                .btn { display: inline-block; background-color: #7c3aed; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500; }
                .footer { border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 24px; font-size: 13px; color: #6b7280; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>{$heading}</h2>
            </div>
            <div class="body-content">
        {$inner}
            </div>
            <div class="footer">
                <p>This email was sent from {{app_name}}.</p>
                <p>If you weren't expecting this email, you can safely ignore it.</p>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * @return array<int, array{slug: string, name: string, subject: string, body_html: string, variables: array<int, string>}>
     */
    private function templates(): array
    {
        return [
            [
                'slug' => 'welcome',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to {{app_name}}, {{user_name}}!',
                'body_html' => $this->shell('Welcome to {{app_name}}!', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>Thank you for joining <strong>{{app_name}}</strong>. Your organization <strong>{{org_name}}</strong> is ready to go.</p>
                <p>Sign in any time to start capturing meetings, minutes, and action items.</p>
                <p><a href="{{login_url}}" class="btn">Go to your dashboard</a></p>
                HTML),
                'variables' => ['user_name', 'app_name', 'org_name', 'login_url'],
            ],
            [
                'slug' => 'password-reset',
                'name' => 'Password Reset',
                'subject' => 'Reset your password — {{app_name}}',
                'body_html' => $this->shell('Password Reset', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>We received a request to reset your password. Click the button below to choose a new one.</p>
                <p><a href="{{reset_url}}" class="btn">Reset password</a></p>
                <p>This link will expire shortly. If you didn't request a reset, no action is needed.</p>
                HTML),
                'variables' => ['user_name', 'app_name', 'reset_url'],
            ],
            [
                'slug' => 'email-verification',
                'name' => 'Email Verification',
                'subject' => 'Verify your email address — {{app_name}}',
                'body_html' => $this->shell('Verify Your Email', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>Please confirm your email address to finish setting up your {{app_name}} account.</p>
                <p><a href="{{verify_url}}" class="btn">Verify email address</a></p>
                <p>If you did not create an account, you can ignore this email.</p>
                HTML),
                'variables' => ['user_name', 'app_name', 'verify_url'],
            ],
            [
                'slug' => 'organization-invitation',
                'name' => 'Organization Invitation',
                'subject' => "You've been invited to join {{org_name}}",
                'body_html' => $this->shell('Join {{org_name}}', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p><strong>{{inviter_name}}</strong> has invited you to join <strong>{{org_name}}</strong> on {{app_name}} as a <strong>{{role}}</strong>.</p>
                <p>Click the button below to accept. If you don't have an account yet, you'll be able to create one.</p>
                <p><a href="{{accept_url}}" class="btn">Accept invitation</a></p>
                <p style="font-size: 13px; color: #6b7280;">This invitation expires on {{expires_at}}.</p>
                HTML),
                'variables' => ['user_name', 'inviter_name', 'org_name', 'role', 'accept_url', 'expires_at', 'app_name'],
            ],
            [
                'slug' => 'meeting-invite',
                'name' => 'Meeting Invitation',
                'subject' => 'You are invited to: {{meeting_title}}',
                'body_html' => $this->shell('Meeting Invitation', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>You have been invited to a meeting on {{app_name}}.</p>
                <div class="info">
                    <p><strong>Meeting:</strong> {{meeting_title}}</p>
                    <p><strong>When:</strong> {{meeting_date}}</p>
                    <p><strong>Organization:</strong> {{org_name}}</p>
                </div>
                <p><a href="{{meeting_url}}" class="btn">View meeting details</a></p>
                HTML),
                'variables' => ['user_name', 'meeting_title', 'meeting_date', 'meeting_url', 'org_name', 'app_name'],
            ],
            [
                'slug' => 'meeting-agenda',
                'name' => 'Meeting Agenda',
                'subject' => 'Agenda: {{meeting_title}}',
                'body_html' => $this->shell('{{meeting_title}}', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>Here is the agenda for <strong>{{meeting_title}}</strong> scheduled on {{meeting_date}}.</p>
                <div class="info">
                    <p>{{agenda_body}}</p>
                </div>
                <p><a href="{{meeting_url}}" class="btn">View meeting details</a></p>
                HTML),
                'variables' => ['user_name', 'meeting_title', 'meeting_date', 'agenda_body', 'meeting_url', 'app_name'],
            ],
            [
                'slug' => 'meeting-finalized',
                'name' => 'Meeting Minutes Finalized',
                'subject' => 'Minutes finalized: {{meeting_title}}',
                'body_html' => $this->shell('Minutes Finalized', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>The minutes for <strong>{{meeting_title}}</strong> ({{org_name}}) have been finalized. Please review and take action on any items assigned to you.</p>
                <p><a href="{{meeting_url}}" class="btn">Review minutes</a></p>
                HTML),
                'variables' => ['user_name', 'meeting_title', 'meeting_url', 'org_name', 'app_name'],
            ],
            [
                'slug' => 'mom-distribution',
                'name' => 'Minutes of Meeting Distribution',
                'subject' => 'Minutes of Meeting: {{meeting_title}}',
                'body_html' => $this->shell('{{meeting_title}}', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>{{body_note}}</p>
                <p>Please find the Minutes of Meeting for <strong>{{meeting_title}}</strong> attached to this email.</p>
                <p><a href="{{meeting_url}}" class="btn">View meeting</a></p>
                HTML),
                'variables' => ['user_name', 'meeting_title', 'body_note', 'meeting_url', 'org_name', 'app_name'],
            ],
            [
                'slug' => 'meeting-follow-up',
                'name' => 'Meeting Follow-up',
                'subject' => 'Follow-up: {{meeting_title}}',
                'body_html' => $this->shell('{{meeting_title}}', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>Thank you for attending <strong>{{meeting_title}}</strong>. Here is a summary and the next steps.</p>
                <div class="info">
                    <p>{{follow_up_body}}</p>
                </div>
                <p><a href="{{meeting_url}}" class="btn">View meeting details</a></p>
                HTML),
                'variables' => ['user_name', 'meeting_title', 'follow_up_body', 'meeting_url', 'app_name'],
            ],
            [
                'slug' => 'action-item-assigned',
                'name' => 'Action Item Assigned',
                'subject' => 'New action item assigned: {{action_title}}',
                'body_html' => $this->shell('Action Item Assigned', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>You have been assigned a new action item from <strong>{{meeting_title}}</strong>.</p>
                <div class="info">
                    <p><strong>Task:</strong> {{action_title}}</p>
                    <p><strong>Due:</strong> {{due_date}}</p>
                </div>
                <p><a href="{{action_url}}" class="btn">View action item</a></p>
                HTML),
                'variables' => ['user_name', 'action_title', 'due_date', 'meeting_title', 'action_url', 'app_name'],
            ],
            [
                'slug' => 'action-item-reminder',
                'name' => 'Action Item Reminder',
                'subject' => 'Reminder: {{action_title}} is due {{due_date}}',
                'body_html' => $this->shell('Action Item Reminder', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>This is a reminder that your action item from <strong>{{meeting_title}}</strong> is due soon.</p>
                <div class="info">
                    <p><strong>Task:</strong> {{action_title}}</p>
                    <p><strong>Due:</strong> {{due_date}}</p>
                </div>
                <p><a href="{{action_url}}" class="btn">View action item</a></p>
                HTML),
                'variables' => ['user_name', 'action_title', 'due_date', 'meeting_title', 'action_url', 'app_name'],
            ],
            [
                'slug' => 'report-ready',
                'name' => 'Report Ready',
                'subject' => 'Your report is ready: {{report_name}}',
                'body_html' => $this->shell('Report Ready', <<<'HTML'
                <p>Hi {{user_name}},</p>
                <p>Your report has been generated and is ready for download.</p>
                <div class="info">
                    <p><strong>Report:</strong> {{report_name}}</p>
                    <p><strong>Type:</strong> {{report_type}}</p>
                    <p><strong>Generated:</strong> {{generated_at}}</p>
                </div>
                <p><a href="{{download_url}}" class="btn">Download report</a></p>
                HTML),
                'variables' => ['user_name', 'report_name', 'report_type', 'generated_at', 'download_url', 'app_name'],
            ],
        ];
    }
}
