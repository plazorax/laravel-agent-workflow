# Configuration

## Requirements

Claude Code, `php` on `PATH` (8.2+), git, and a Laravel project. Pint ships with new Laravel applications. Larastan and Pest are optional; each gate is skipped when its tool is not installed.

## Recommended project setup

See the [Boost](https://laravel.com/docs/boost), [Larastan](https://github.com/larastan/larastan) and [Pest](https://pestphp.com/docs/installation) docs.

```bash
composer require laravel/boost --dev && php artisan boost:install
composer require --dev "larastan/larastan:^3.0"   # then add a phpstan.neon
```

Optionally copy [`templates/CLAUDE.md`](../templates/CLAUDE.md) into the project and fill in the placeholders. If Boost regenerates `CLAUDE.md` in your project, move that content to `.ai/guidelines/project.md` so Boost includes it instead.

## Installing from a local checkout

To load a local checkout for one session instead of installing from the marketplace:

```bash
git clone https://github.com/plazorax/laravel-agent-workflow.git
claude --plugin-dir ./laravel-agent-workflow
```

## Environment variables

Set these in the environment, for example in `.claude/settings.json` under `env`.

| Variable | Default | Effect |
| --- | --- | --- |
| `LARAVEL_GATES_DISABLE` | unset | `1` turns off the `Stop` gates. |
| `LARAVEL_GATES_TEST_COMMAND` | auto | Replaces the detected test command, for example `vendor/bin/pest --parallel` or `./vendor/bin/sail test`. Run through `/bin/sh`. |
| `LARAVEL_GATES_MAX_ATTEMPTS` | `3` | Consecutive times the `Stop` hook may block before it lets the agent stop and shows the remaining failures to you. |
| `LARAVEL_GATES_TIMEOUT` | `240` | Seconds allowed for each gate command. Claude Code cancels the whole `Stop` hook after 600 seconds (set in [`hooks/hooks.json`](../hooks/hooks.json)), so keep two gates within that. |
| `LARAVEL_GATES_PHPSTAN_MEMORY` | `1G` | Value for `phpstan --memory-limit`. |
| `LARAVEL_GATES_PINT_TIMEOUT` | `30` | Seconds allowed for Pint on one file. |
