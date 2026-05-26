<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Marketing\Models\MarketingSetting;
use Noerd\Marketing\Services\TenantSmtpResolver;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public const DETAIL_CLASS = MarketingSetting::class;

    public $modelId = null;

    #[Locked]
    public ?int $clientId = null;

    public array $settingsData = [];

    public ?string $testEmailMessage = null;

    public ?string $testEmailError = null;

    public function mount(): void
    {
        $this->initDetail();
        $this->clientId = auth()->user()->selected_tenant_id;

        $settings = MarketingSetting::forTenant($this->clientId);

        $this->settingsData = $settings?->toArray() ?? [
            'tenant_id' => $this->clientId,
            'from_email' => null,
            'reply_email' => null,
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_encryption' => null,
            'smtp_username' => null,
            'smtp_password' => null,
        ];
    }

    public function store(): void
    {
        MarketingSetting::updateOrCreate(
            ['tenant_id' => $this->clientId],
            collect($this->settingsData)->except(['id', 'tenant_id', 'created_at', 'updated_at'])->all(),
        );

        $this->showSuccessIndicator = true;
    }

    #[Computed]
    public function canSendTestEmail(): bool
    {
        return ! Cache::has($this->testEmailCacheKey());
    }

    public function sendTestEmail(): void
    {
        $this->testEmailMessage = null;
        $this->testEmailError = null;

        if (! $this->canSendTestEmail) {
            $this->testEmailError = __('Send test email (only possible once per minute)');

            return;
        }

        $user = auth()->user();
        if (! $user || ! $user->email) {
            $this->testEmailError = __('No email address available for the current user.');

            return;
        }

        $settings = (object) array_merge($this->settingsData, ['tenant_id' => $this->clientId]);
        $fromEmail = $this->settingsData['from_email'] ?? null ?: config('mail.from.address');

        try {
            app(TenantSmtpResolver::class)->resolve($settings)
                ->raw(__('This is a test email from the Marketing module.'), function ($message) use ($user, $fromEmail): void {
                    $message->to($user->email)
                        ->from($fromEmail)
                        ->subject(__('Marketing Test Email'));
                });
        } catch (\Throwable $e) {
            $this->testEmailError = $e->getMessage();

            return;
        }

        Cache::put($this->testEmailCacheKey(), now()->timestamp + 60, 60);
        $this->testEmailMessage = __('Test email sent to :email', ['email' => $user->email]);
    }

    protected function testEmailCacheKey(): string
    {
        return 'marketing-test-email-cooldown:' . ($this->clientId ?? 'new') . ':' . auth()->id();
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Marketing Settings') }}</x-noerd::modal-title>
    </x-slot>

    <x-noerd::box>
        <x-noerd::title>{{ __('Mail Settings') }}</x-noerd::title>

        <p class="mt-2 text-sm text-slate-600">
            {{ __('If you provide SMTP credentials, customer emails will be sent via your own mail server. Leave blank to use the default mail server.') }}
        </p>

        <div class="mt-2">
            <x-noerd::input-label>{{ __('From Email') }}</x-noerd::input-label>
            <x-noerd::text-input type="email" wire:model="settingsData.from_email"/>
            <x-noerd::input-error :messages="$errors->get('settingsData.from_email')" class="mt-2"/>
        </div>

        <div class="mt-2">
            <x-noerd::input-label>{{ __('Reply Email') }}</x-noerd::input-label>
            <x-noerd::text-input type="email" wire:model="settingsData.reply_email"/>
            <x-noerd::input-error :messages="$errors->get('settingsData.reply_email')" class="mt-2"/>
        </div>

        <div class="mt-2">
            <x-noerd::input-label>{{ __('SMTP Host') }}</x-noerd::input-label>
            <x-noerd::text-input wire:model="settingsData.smtp_host"/>
            <x-noerd::input-error :messages="$errors->get('settingsData.smtp_host')" class="mt-2"/>
        </div>

        <div class="mt-2">
            <x-noerd::input-label>{{ __('SMTP Port') }}</x-noerd::input-label>
            <x-noerd::text-input type="number" wire:model="settingsData.smtp_port"/>
            <x-noerd::input-error :messages="$errors->get('settingsData.smtp_port')" class="mt-2"/>
        </div>

        <div class="mt-2">
            <x-noerd::input-label>{{ __('SMTP Encryption') }}</x-noerd::input-label>
            <x-noerd::select-input wire:model="settingsData.smtp_encryption">
                <option value="">{{ __('None') }}</option>
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
            </x-noerd::select-input>
            <x-noerd::input-error :messages="$errors->get('settingsData.smtp_encryption')" class="mt-2"/>
        </div>

        <div class="mt-2">
            <x-noerd::input-label>{{ __('SMTP Username') }}</x-noerd::input-label>
            <x-noerd::text-input wire:model="settingsData.smtp_username"/>
            <x-noerd::input-error :messages="$errors->get('settingsData.smtp_username')" class="mt-2"/>
        </div>

        <div class="mt-2" x-data="{ showPassword: false }">
            <x-noerd::input-label>{{ __('SMTP Password') }}</x-noerd::input-label>
            <div class="relative">
                <x-noerd::text-input
                    type="password"
                    x-bind:type="showPassword ? 'text' : 'password'"
                    wire:model="settingsData.smtp_password"
                    class="!pr-20"/>
                <button type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-2 my-auto text-sm text-gray-600 hover:text-gray-900">
                    <span x-show="!showPassword">{{ __('Show') }}</span>
                    <span x-show="showPassword" style="display: none;">{{ __('Hide') }}</span>
                </button>
            </div>
            <x-noerd::input-error :messages="$errors->get('settingsData.smtp_password')" class="mt-2"/>
        </div>

        <div class="mt-4 border-t pt-4">
            <x-noerd::button
                    type="button"
                    variant="secondary"
                    wire:click="sendTestEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendTestEmail"
                    :disabled="! $this->canSendTestEmail">
                <span wire:loading.remove wire:target="sendTestEmail">
                    {{ __('Send test email (only possible once per minute)') }}
                </span>
                <span wire:loading wire:target="sendTestEmail" style="display: none;">
                    {{ __('Sending...') }}
                </span>
            </x-noerd::button>

            @if($testEmailMessage)
                <p class="mt-2 text-sm text-green-700">{{ $testEmailMessage }}</p>
            @endif
            @if($testEmailError)
                <p class="mt-2 text-sm text-red-700">{{ $testEmailError }}</p>
            @endif
        </div>
    </x-noerd::box>

    <x-slot:footer>
        <div class="ml-auto flex">
            <x-noerd::delete-save-bar class="relative" :show-delete="false"></x-noerd::delete-save-bar>
        </div>
    </x-slot:footer>
</x-noerd::page>
