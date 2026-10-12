# Project instructions

<!--
  Template from laravel-agent-workflow. Copy to the root of a Laravel project
  and adjust. Keep it short: Laravel Boost already writes the framework and
  package guidelines (run `php artisan boost:install` / `boost:update`), and
  durable project rules belong in `.ai/rules/` via Boost's record-rule tool.
  If Boost regenerates CLAUDE.md in your project, move this content to
  `.ai/guidelines/project.md` so Boost includes it instead.
-->

## Guidelines

- Laravel Boost guidelines are the source of truth for Laravel, Livewire, Inertia, Pest, Pint and Tailwind conventions. Follow them and use the Boost `search-docs` tool for version-specific APIs.
- Before editing a file, check `.ai/rules/index.md` for rules that apply to its path.

## This project

- Purpose: <one sentence about what the application does>
- Stack: PHP <version>, Laravel <version>, <database>, <frontend: Blade / Livewire / Inertia + React or Vue>
- Run locally: <for example `composer run dev`, Herd, Sail>
- Domain notes: <terms, invariants and integrations an agent cannot infer from the code>

## Workflow

- Work test-first for new behaviour (`/laravel-agent-workflow:tdd <feature>`). Every change ships with a Pest test that fails without it.
- Validate input with Form Requests and authorize with policies. Persist only `$request->validated()`.
- Do not edit `.env`, `vendor/`, `storage/` or `composer.lock`; the laravel-agent-workflow hooks block it. Add new configuration keys to `.env.example` and `config/*.php`.
- Before finishing, the Stop hook runs Larastan and the dirty tests. Fix what it reports; do not suppress errors or weaken tests.
- Before a pull request, run `/laravel-agent-workflow:review`.

## Commands

- Tests: `vendor/bin/pest` (or `php artisan test`)
- Format: `vendor/bin/pint --dirty`
- Static analysis: `vendor/bin/phpstan analyse`
