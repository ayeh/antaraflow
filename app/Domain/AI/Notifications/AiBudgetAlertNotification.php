<?php

declare(strict_types=1);

namespace App\Domain\AI\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AiBudgetAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  'warning'|'critical'|'anomaly'  $level
     */
    public function __construct(
        public string $level,
        public float $spend,
        public float $threshold,
        public bool $autoDisabled = false,
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
        $greeting = match ($this->level) {
            'critical' => __('AI spend limit reached'),
            'anomaly' => __('AI spend anomaly detected'),
            default => __('AI spend warning'),
        };

        $thresholdLabel = $this->level === 'anomaly'
            ? __('Rolling baseline threshold: :threshold.', ['threshold' => $this->formatUsd($this->threshold)])
            : __('Configured threshold: :threshold.', ['threshold' => $this->formatUsd($this->threshold)]);

        $mail = (new MailMessage)
            ->subject($this->subjectLine())
            ->greeting($greeting)
            ->line(__('Today\'s estimated AI API spend is :spend.', ['spend' => $this->formatUsd($this->spend)]))
            ->line($thresholdLabel);

        if ($this->autoDisabled) {
            $mail->line(__('AI features have been automatically DISABLED to stop further spend.'));
        }

        return $mail->action(__('Open AI Control Panel'), route('admin.ai.index'));
    }

    public function toTelegram(object $notifiable): string
    {
        $emoji = $this->level === 'critical' ? '🚨' : '⚠️';
        $lines = [
            "{$emoji} <b>{$this->subjectLine()}</b>",
            '',
            __('Today\'s AI spend: :spend', ['spend' => $this->formatUsd($this->spend)]),
            __('Threshold: :threshold', ['threshold' => $this->formatUsd($this->threshold)]),
        ];

        if ($this->autoDisabled) {
            $lines[] = '';
            $lines[] = '🛑 '.__('AI features have been automatically DISABLED.');
        }

        return implode("\n", $lines);
    }

    private function subjectLine(): string
    {
        return match ($this->level) {
            'critical' => __('[antaraNote] AI hard cap exceeded'),
            'anomaly' => __('[antaraNote] AI spend anomaly detected'),
            default => __('[antaraNote] AI daily budget warning'),
        };
    }

    private function formatUsd(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
