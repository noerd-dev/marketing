@verbatim
## Communication Module

Central e-mail sending and communications log (Composer package `noerd/communication`, namespace
`Noerd\Communication`). It is a Noerd tenant app (`app-configs/communication/navigation.yml`, the
app is `hidden: true` in the sidebar — its screens are reached through the settings of the host
app). The framework rules (lists, details, pages, modals, themes, tests, translations) come from
the `noerd/noerd` guideline — this block only adds what is specific to this module.

### Domain
- `Communication` (table `communications`, `BelongsToTenant`, `tenant_id` nullable) — one row per
  outgoing mail: `type` (`CommunicationType`, only `email`), `status` (`CommunicationStatus`:
  `queued`, `sent`, `failed`), `from`, `to`, `subject`, `body`, `mailable_class`, `message_id`,
  `error_message`, `metadata` (JSON), `sent_at`. TWO independent polymorphic links without
  foreign keys: `model()` (`model_type`/`model_id` — the SOURCE record the mail was generated
  from, e.g. an order) and `contact()` (`contact_type`/`contact_id` — the record the mail
  CONCERNS, e.g. the ordering party). Any Eloquent model may be linked; the module knows no
  domain model
- `MailSender` (table `communication_mail_senders`) — one outgoing sender account: `name`,
  `from_email`, `reply_email`, `smtp_host`/`smtp_port`/`smtp_encryption`/`smtp_username`,
  `smtp_password` (cast `encrypted`), `is_default`, `is_active`. Exactly one active account per
  tenant carries `is_default` — enforced in `booted()`: the first account of a tenant becomes
  the default, promoting one demotes the others, deactivating demotes, and
  `electDefaultForTenant()` promotes the first active account after a demotion/delete. No
  default account means the `.env` mailer; there is NO toggle. `usesCustomSmtp()` = host AND
  username filled — an account without credentials is still valid (carries from/reply, relays
  through the platform mailer). `defaultForTenant()` is unscoped (queue workers carry no tenant)
- `CommunicationSetting` (table `communication_settings`, ONE row per tenant, unique
  `tenant_id`) — read through `CommunicationSetting::forTenant($tenantId)` (unscoped);
  `resolvedFromEmail()` / `resolvedReplyEmail()` answer from the tenant's default `MailSender`,
  falling back to `config('mail.from.address')` / null. The row's own `from_email` is
  deliberately NOT consulted (SPF/DKIM). The SMTP columns and `reply_email` were moved to
  `communication_mail_senders` by the `2026_09_01_*` migrations; the settings page is gone
  (its old URLs redirect to `/mail-senders`)
- None of the models carries `custom_attributes`; project-specific fields stay out of the module

### Sending mail
- EVERY application mail goes through `app(Communicator::class)->send(Mailable $mailable,
  string|array|Model|null $to, ?Model $contact = null, mixed $tenantSettings = null,
  array $metadata = [], bool $queue = false, ?Model $model = null, ?MailSender $sender = null): ?Communication`
  — never `Mail::to()` directly. `to:` accepts an address, a list, or a model with an `email`
  attribute (a model without e-mail → returns `null`, nothing sent). `contact:` falls back to
  `to:` when that is a model. Pass the source record as `model:`
- The row is written FIRST (status `sent`, or `queued` with `queue: true`), the Mailable is
  tagged with the `X-Communication-Id` header (`Communicator::COMMUNICATION_HEADER`), then sent
  through the resolved mailer. A throwing mailer marks the row `failed` + `error_message` and
  RE-THROWS, so job retry logic stays intact — never swallow the exception in the caller
- Tenant resolution: explicit `sender->tenant_id` → `tenantSettings['tenant_id']` /
  `->tenant_id` → `contact->tenant_id` → the authenticated user's `selected_tenant_id` → null
- `LogMessageSentFallback` listens to `Illuminate\Mail\Events\MessageSent`: a message carrying
  the header completes the existing row (`from`, `subject`, `body`, `message_id`, status
  `sent`); a message WITHOUT it (sent past the Communicator) is logged as an untracked row with
  `tenant_id = null`. Subject/from/body are therefore filled by the listener — a Mailable needs
  no `envelope()`
- Mailer choice is `TenantSmtpResolver` (singleton): `resolveForSender(?MailSender)` →
  `resolveForTenant(?int)` → `resolve(mixed)` (accepts a `MailSender`, anything with
  `tenant_id`, or null). An explicit `sender:` wins over the tenant default. A custom-SMTP
  account is registered at runtime as `mail.mailers.communication_sender_{id}_{fingerprint}` —
  the fingerprint of the credentials is part of the name because `MailManager` caches mailers
  forever (stale transports in long-lived queue workers). `smtp_encryption = ssl` forces
  `scheme: smtps`; tls is left to Laravel's port heuristic
- A Mailable that builds its own envelope reads the addresses from
  `CommunicationSetting::forTenant($tenantId)?->resolvedFromEmail()` / `resolvedReplyEmail()`
  (reference: `accounting` `InvoiceMail`) — never from `.env` directly and never from the
  settings row's own columns

### Structure
- Livewire components (flat, `resources/views/components/`, namespace `communication::`):
  `communications-list` (custom `listData()` narrowing by `modelType` + `modelId` — embed it
  in a detail/page to show the mails of one record; `rendering()` opens
  `?communicationId=` as a modal), `communication-detail` (slim, read-only log entry),
  `mail-senders-list` (slim), `mail-sender-detail` (YAML action `sendTestEmail`: sends a raw
  test mail THROUGH THIS ACCOUNT to the logged-in user, cached cooldown of one minute per
  sender and user). The provider registers the namespace AND `Livewire::addLocation`
- YAML: `app-configs/communication/{lists,details}/` + `navigation.yml` — keep the module copy
  and the installed project copy (`app-configs/communication/…`) in sync
- Routes: `routes/communication-routes.php` — middleware `['noerd']` only (no
  `app-access` gate), names `communications`, `communication.detail`, `mail-senders`,
  `mail-sender.detail`; legacy `/sent-mails`, `/sent-mail/{id}`, `/marketing-settings` and
  `/communication-settings` are 301 redirects
- Tenant app name is `COMMUNICATION` (uppercase; renamed from `MARKETING` by the
  `rename_marketing_tenant_app_to_communication` migration) — gates and test traits compare
  exactly. App icon: `communication::icons.app`, app route `communications`
- Services are container singletons (`TenantSmtpResolver`, `Communicator`); the `MessageSent`
  listener is registered in the provider's `boot()`
- Translations: `resources/lang/de.json` (English keys); factories for all three models in
  `database/factories/`
- Depends only on `noerd/noerd` (guarded by `tests/ModuleBoundaryTest.php`) — never on a domain
  module; consumers (`accounting`, `booking`, `liefertool`) depend on this module, not the other
  way round

### Commands
- `php artisan noerd:install-communication` — installs YAML configs, registers the tenant app,
  runs migrations (`HasModuleInstallation` + `RequiresNoerdInstallation`)
- `php artisan noerd:update-communication` — idempotent YAML update (picked up by
  `noerd:update-all`)
- `php artisan communication:delete-old-communications {--days=30}` — deletes log rows older
  than the retention (unscoped, all tenants). It is NOT scheduled by the module — the host
  schedules it

### Tests
- Pest tests in `tests/` (host-bound, `Tests\TestCase` of the host + `RefreshDatabase`); run
  from the host: `php artisan test --compact app-modules/communication/tests`
- `tests/Traits/CreatesCommunicationUser.php` provides `withCommunicationModule()` (tenant +
  user + `COMMUNICATION` tenant app, selected app set)
- `Mail::fake()` / a bound fake `Mailer` for the Communicator; assert rows through
  `Communication::withoutGlobalScopes()` — the listener and the resolver run without tenant
  context
- Prove mechanics, never the current YAML configuration (see the `noerd-testing` skill)

### Reference implementations
- `src/Services/Communicator.php` — log-first sending with re-thrown failures
- `src/Services/TenantSmtpResolver.php` — runtime mailer registration keyed by credential
  fingerprint
- `src/Models/MailSender.php` — tenant singleton-default invariant kept in model events
  (`creating` vs. `saving` ordering with `BelongsToTenant`)
- `communications-list.blade.php` — slim list with a narrowing `listData()` override
- `mail-sender-detail.blade.php` + `details/mail-sender-detail.yml` — a YAML `action:` with a
  rate-limited component method
@endverbatim
