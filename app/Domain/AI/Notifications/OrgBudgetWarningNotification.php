<?php

declare(strict_types=1);

namespace App\Domain\AI\Notifications;

use App\Domain\AI\Models\OrganizationAiBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrgBudgetWarningNotification extends Notification
{
    use Queueable;

    /**
     * @param  'org_warning'|'org_critical'  $level
     */
    public function __construct(
        public string $level,
        public string $orgName,
        public float $utilisation,
        public OrganizationAiBudget $budget,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->routeNotificationFor('mail')) {
            $channels[] = 'mail';
        }

        if ($notifiable->routeNotificationFor('telegram')) {
            $channels[] = 'telegram';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pct = round($this->utilisation * 100);
        $isCritical = $this->level === 'org_critical';

        $mail = (new MailMessage)
            ->subject($this->subjectLine())
            ->greeting($isCritical ? __('Organisation AI budget exceeded') : __('Organisation AI budget warning'))
            ->line(__(':org has used :pct% of its AI budget.', ['org' => $this->orgName, 'pct' => $pct]));

        if ($this->budget->daily_limit > 0) {
            $mail->line(__('Daily limit: $:limit', ['limit' => number_format($this->budget->daily_limit, 2)]));
        }

        if ($this->budget->monthly_limit > 0) {
            $mail->line(__('Monthly limit: $:limit', ['limit' => number_format($this->budget->monthly_limit, 2)]));
        }

        if ($isCritical) {
            $mail->line(__('AI calls for this organisation are now being blocked.'));
        }

        return $mail->action(__('Open AI Control Panel'), route('admin.ai.org-budgets'));
    }

    public function toTelegram(object $notifiable): string
    {
        $pct = round($this->utilisation * 100);
        $emoji = $this->level === 'org_critical' ? '🚨' : '⚠️';

        $lines = [
            "{$emoji} <b>{$this->subjectLine()}</b>",
            '',
            __(':org has used :pct% of its AI budget.', ['org' => $this->orgName, 'pct' => $pct]),
        ];

        if ($this->budget->daily_limit > 0) {
            $lines[] = __('Daily limit: $:limit', ['limit' => number_format($this->budget->daily_limit, 2)]);
        }

        if ($this->budget->monthly_limit > 0) {
            $lines[] = __('Monthly limit: $:limit', ['limit' => number_format($this->budget->monthly_limit, 2)]);
        }

        if ($this->level === 'org_critical') {
            $lines[] = '';
            $lines[] = '🛑 '.__('AI calls for this organisation are now being blocked.');
        }

        return implode("\n", $lines);
    }

    private function subjectLine(): string
    {
        $pct = round($this->utilisation * 100);

        return $this->level === 'org_critical'
            ? __('[antaraNote] :org AI budget exceeded (:pct%)', ['org' => $this->orgName, 'pct' => $pct])
            : __('[antaraNote] :org AI budget at :pct%', ['org' => $this->orgName, 'pct' => $pct]);
    }
}
