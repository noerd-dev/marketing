# AGENTS.md — noerd/communication

Contributor notes for humans and AI agents working on the Communication module. The rules for
building WITH noerd (lists, details, pages, modals, modules, tests) come from the `noerd/noerd`
Boost guideline and skills; the module-specific rules are in
`resources/boost/guidelines/core.blade.php`. Both are rendered into the host project's agent files
by `php artisan boost:update` (add `noerd/communication` to the `packages` array in `boost.json`).

## What this module is

The central mail channel of a noerd installation: every application e-mail is sent through
`Communicator::send()`, which writes a row to the `communications` log first and only then hands
the Mailable to the mailer resolved by `TenantSmtpResolver` (an explicit `MailSender`, the tenant's
default sender account, or the `.env` mailer). Sender accounts (`communication_mail_senders`) carry
from/reply addresses and optional encrypted SMTP credentials; the `MessageSent` listener completes
the log row (and records mails sent past the Communicator as untracked rows). The module knows no
domain model — both record links of a communication are polymorphic and free of foreign keys.

## Layout

- `app-configs/communication/` — YAML templates (lists/, details/, navigation.yml); the installed
  copy lives in the host's `app-configs/communication/` — change both
- `app-configs/stubs/add_communication_tenant_app.php.stub` — the idempotent tenant-app migration
  published by `noerd:install-communication`
- `resources/views/components/` — Livewire single-file components (`communications-list`,
  `communication-detail`, `mail-senders-list`, `mail-sender-detail`), flat, Livewire namespace
  `communication::`; `icons/app.blade.php` is the tenant-app icon
- `src/Models/` (`Communication`, `MailSender`, `CommunicationSetting`), `src/Enums/`,
  `src/Services/` (`Communicator`, `TenantSmtpResolver`), `src/Listeners/LogMessageSentFallback.php`,
  `src/Commands/` (install, update, `Crons/DeleteOldCommunications`),
  `src/Providers/CommunicationServiceProvider.php`
- `database/migrations|factories/`, `tests/` (Pest), `resources/lang/de.json`
- Tables: `communications`, `communication_settings`, `communication_mail_senders`; the tenant
  app name is `COMMUNICATION` (uppercase)

## Commands

- `php artisan noerd:install-communication` — first installation (asks for the tenant assignment)
- `php artisan noerd:update-communication` — idempotent YAML update, discovered by `noerd:update-all`
- `php artisan communication:delete-old-communications --days=30` — retention housekeeping;
  schedule it in the host, the module does not

## Working on the module

- Tests are host-bound (`Tests\TestCase` + `RefreshDatabase`). From the host project root:
  `php artisan test --compact app-modules/communication/tests`. Tests prove mechanics, never the
  current YAML configuration. `Noerd\Communication\Tests\` stays in the production `autoload` of
  `composer.json`: a path-repository's `autoload-dev` is not loaded by the host
- Format from the host project root with an explicit path:
  `vendor/bin/pint app-modules/communication` (a plain `--dirty` run silently skips submodule files)
- Keep the module independent of other optional modules: it depends on `noerd/noerd` only
  (`tests/ModuleBoundaryTest.php`); consumers such as accounting, booking or liefertool depend on
  this module, never the other way round. Do not add domain foreign keys to the polymorphic links
- The models carry no `custom_attributes`; project-specific fields must not go into module code
  or module YAML
- When a feature changes: update the YAML in both places, `resources/lang/de.json`, the tests,
  `resources/boost/guidelines/core.blade.php` and `README.md`
- Releasing: bump `"version"` in `composer.json` to the tag in the tagged commit
