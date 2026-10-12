# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-10-13

### Added

- `PreToolUse` hook that denies `Edit`/`Write` on `.env*` (except `.env.example`), `vendor/`, `storage/` and `composer.lock`, with a reason Claude can act on.
- `PostToolUse` hook that runs Laravel Pint on the single PHP file that was just written, when `vendor/bin/pint` exists.
- `Stop` hook that runs Larastan and `pest --dirty` (or `php artisan test`) when PHP files have uncommitted changes, and reports failures back to Claude, with a cap on consecutive fix attempts.
- `pr-reviewer` and `security-check` agents.
- `/laravel-agent-workflow:tdd` and `/laravel-agent-workflow:review` skills.
- `templates/CLAUDE.md` project template that defers to Laravel Boost.
- PHPUnit tests for the hook scripts and a CI smoke test against a fresh Laravel application.

[0.1.0]: https://github.com/plazorax/laravel-agent-workflow/releases/tag/v0.1.0
