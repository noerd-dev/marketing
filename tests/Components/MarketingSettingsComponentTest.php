<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Noerd\Marketing\Models\MarketingSetting;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

function actingAsMarketingUser(): NoerdUser
{
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user->id);
    test()->actingAs($user);

    return $user;
}

it('renders the marketing-settings route', function (): void {
    actingAsMarketingUser();

    $this->get('/marketing-settings')->assertStatus(200);
});

it('persists marketing settings on save', function (): void {
    $user = actingAsMarketingUser();

    Livewire::test('marketing::marketing-settings-detail')
        ->set('settingsData.from_email', 'from@example.com')
        ->set('settingsData.reply_email', 'reply@example.com')
        ->set('settingsData.smtp_host', 'smtp.example.com')
        ->set('settingsData.smtp_port', 587)
        ->set('settingsData.smtp_encryption', 'tls')
        ->set('settingsData.smtp_username', 'user@example.com')
        ->set('settingsData.smtp_password', 'secret')
        ->call('store')
        ->assertSet('showSuccessIndicator', true);

    $saved = MarketingSetting::forTenant($user->selected_tenant_id);
    expect($saved)->not->toBeNull();
    expect($saved->from_email)->toBe('from@example.com');
    expect($saved->smtp_host)->toBe('smtp.example.com');
    expect($saved->smtp_port)->toBe(587);
});

it('sends a test email to the logged-in user', function (): void {
    $user = actingAsMarketingUser();
    app('mail.manager')->forgetMailers();
    Cache::flush();

    Livewire::test('marketing::marketing-settings-detail')
        ->call('sendTestEmail')
        ->assertSet('testEmailError', null)
        ->assertSet('testEmailMessage', __('Test email sent to :email', ['email' => $user->email]));

    $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1);
    expect($messages->first()->getEnvelope()->getRecipients()[0]->getAddress())->toBe($user->email);
});

it('rate-limits the test email to once per minute', function (): void {
    $user = actingAsMarketingUser();
    app('mail.manager')->forgetMailers();
    Cache::flush();

    $component = Livewire::test('marketing::marketing-settings-detail');
    $component->call('sendTestEmail')
        ->assertSet('testEmailMessage', __('Test email sent to :email', ['email' => $user->email]));
    $component->call('sendTestEmail')
        ->assertSet('testEmailError', __('Send test email (only possible once per minute)'));
});
