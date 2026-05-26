<?php

namespace Noerd\Marketing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Marketing\Models\MarketingSetting;
use Noerd\Models\Tenant;

class MarketingSettingFactory extends Factory
{
    protected $model = MarketingSetting::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'from_email' => $this->faker->safeEmail(),
            'reply_email' => null,
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_encryption' => null,
            'smtp_username' => null,
            'smtp_password' => null,
        ];
    }

    public function withSmtp(string $host = 'smtp.example.com', string $username = 'user@example.com'): static
    {
        return $this->state(fn () => [
            'smtp_host' => $host,
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => $username,
            'smtp_password' => 'secret',
        ]);
    }
}
