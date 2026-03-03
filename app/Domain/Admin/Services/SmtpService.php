<?php

declare(strict_types=1);

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\SmtpConfiguration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class SmtpService
{
    public function getGlobalConfig(): ?SmtpConfiguration
    {
        return SmtpConfiguration::query()
            ->whereNull('organization_id')
            ->first();
    }

    public function getConfigForOrganization(int $organizationId): ?SmtpConfiguration
    {
        return SmtpConfiguration::getForOrganization($organizationId);
    }

    public function applyConfig(SmtpConfiguration $config): void
    {
        Config::set('mail.mailers.smtp.host', $config->host);
        Config::set('mail.mailers.smtp.port', $config->port);
        Config::set('mail.mailers.smtp.username', $config->decrypted_username);
        Config::set('mail.mailers.smtp.password', $config->decrypted_password);
        Config::set('mail.mailers.smtp.encryption', $config->encryption);
        Config::set('mail.from.address', $config->from_address);
        Config::set('mail.from.name', $config->from_name);
    }

    public function testConnection(SmtpConfiguration $config, string $testEmail): bool
    {
        $this->applyConfig($config);

        try {
            Mail::raw('This is a test email from antaraFLOW admin panel.', function ($message) use ($testEmail, $config) {
                $message->to($testEmail)
                    ->from($config->from_address, $config->from_name)
                    ->subject('SMTP Test — antaraFLOW');
            });

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
