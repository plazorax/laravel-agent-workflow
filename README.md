# Laravel Agent Workflow

![CI](https://github.com/plazorax/laravel-agent-workflow/actions/workflows/ci.yml/badge.svg)

A [Claude Code](https://code.claude.com/docs/en/overview) plugin that adds quality gates to AI-assisted Laravel development. It runs next to [Laravel Boost](https://github.com/laravel/boost): Boost tells the agent how to write Laravel code, this plugin checks the result.

## Why

- Edits to `.env` or `vendor/` are easy to miss in review. The plugin refuses them and tells the agent why.
- Formatting drift makes noisy diffs. Pint runs on each PHP file right after it changes.
- "Done" should mean static analysis and tests pass. The plugin runs them before the agent may stop.
- Hooks run on every tool call, whether or not the model remembers its instructions.

## Install

Requires `php` 8.2+ on `PATH`, git and a Laravel project. Larastan and Pest are optional; each gate is skipped when its tool is missing.

```text
/plugin marketplace add plazorax/laravel-agent-workflow
/plugin install laravel-agent-workflow@plazorax-laravel
```

Recommended project setup and environment variables: [docs/configuration.md](docs/configuration.md).

## What it does

| Hook | When | What |
| --- | --- | --- |
| Path guard | Before `Edit`/`Write` | Denies edits to `.env*` (except `.env.example`), `vendor/`, `storage/` and `composer.lock` |
| Pint | After `Edit`/`Write` on a `*.php` file | Formats that one file; reports syntax errors |
| Quality gates | On `Stop`, if PHP files changed | Runs Larastan and `pest --dirty`; failures go back to the agent (3 attempts by default) |

Details, output format and internals: [docs/hooks.md](docs/hooks.md).

## What's inside

- `pr-reviewer` agent: Laravel conventions, N+1 queries, validation, authorization and test coverage. Read-only.
- `security-check` agent: OWASP-based review for mass assignment, access control, injection, XSS and secrets. Read-only.
- `/laravel-agent-workflow:tdd <feature>`: test-first workflow with Pest, gated by Pint, Larastan and the tests.
- `/laravel-agent-workflow:review [base]`: runs both agents plus the checks and merges the findings into one report.
- [`templates/CLAUDE.md`](templates/CLAUDE.md): project instructions template that defers to Boost guidelines.

More on agents, skills and their sources: [docs/agents-and-skills.md](docs/agents-and-skills.md).

## Status

v0.1.0 — reference implementation. The hooks are covered by PHPUnit tests and a smoke test against a fresh Laravel app, but have not yet been run in a long interactive session. The path guard sees `Edit` and `Write` only, not writes made through `Bash`.

See [docs/limitations.md](docs/limitations.md) and [docs/development.md](docs/development.md).

## License

[MIT](LICENSE) © 2026 Oleksii Shaposhnikov
