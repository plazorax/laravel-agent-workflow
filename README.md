# laravel-agent-workflow

![CI](https://github.com/plazorax/laravel-agent-workflow/actions/workflows/ci.yml/badge.svg)

A [Claude Code](https://code.claude.com/docs/en/overview) plugin that adds quality gates to AI-assisted Laravel development: it blocks edits to files an agent should not touch, formats each PHP file with Pint as it is written, and runs Larastan and your tests before the agent is allowed to finish. It also ships two review agents and a test-first workflow.

**Status:** v0.1.0, a reference implementation.
**Tested with:** PHP 8.4.25, Composer 2.10.3, PHPUnit 11.5.57, PHPStan 2.3.1, Pint 1.32.1, Claude Code 2.1.282 (`claude plugin validate`), git 2.46.0 on macOS. The smoke test ran locally against Laravel 13.35.0 with Larastan 3.13.0 and Pest 5.3.2. The CI workflow is set up to also run the hook tests on PHP 8.2 and 8.3.

## Why

[Laravel Boost](https://github.com/laravel/boost) gives coding agents Laravel guidelines, skills and an MCP server with documentation search, database schema and logs. It tells the agent how to write Laravel code. It does not check the result.

This plugin covers that part with [Claude Code hooks](https://code.claude.com/docs/en/hooks), which run on every tool call whether or not the model remembers its instructions:

- An agent that edits `.env` or `vendor/` causes problems that are easy to miss in review. The plugin refuses those edits and explains why.
- Formatting drift creates noisy diffs. Pint runs on the one file that changed, right after it changes.
- "Done" should mean the static analysis and the tests pass. The `Stop` hook runs them and sends failures back to the agent to fix.

It is meant to run next to Boost, not to replace it. The agents and the `CLAUDE.md` template defer to Boost's guidelines and `.ai/rules/` when they exist.

## Quick start

Requirements: Claude Code, `php` on `PATH` (8.2+), git, and a Laravel project. Pint ships with new Laravel applications. Larastan and Pest are optional; each gate is skipped when its tool is not installed.

Install from this repository's marketplace:

```text
/plugin marketplace add plazorax/laravel-agent-workflow
/plugin install laravel-agent-workflow@plazorax-laravel
```

Or load a local checkout for one session:

```bash
git clone https://github.com/plazorax/laravel-agent-workflow.git
claude --plugin-dir ./laravel-agent-workflow
```

Recommended project setup (see the [Boost](https://laravel.com/docs/boost), [Larastan](https://github.com/larastan/larastan) and [Pest](https://pestphp.com/docs/installation) docs):

```bash
composer require laravel/boost --dev && php artisan boost:install
composer require --dev "larastan/larastan:^3.0"   # then add a phpstan.neon
```

Optionally copy [`templates/CLAUDE.md`](templates/CLAUDE.md) into the project and fill in the placeholders.

## Usage

### Hooks (automatic)

| Event | Matcher | Script | Behaviour |
| --- | --- | --- | --- |
| `PreToolUse` | `Edit\|Write` | `scripts/guard-paths.php` | Denies edits to `.env` and `.env.*` (except `.env.example`), `vendor/`, `storage/` and `composer.lock`, with a reason the agent can act on. |
| `PostToolUse` | `Edit\|Write` | `scripts/format-php.php` | Runs `vendor/bin/pint <file>` on the edited `*.php` file (not Blade). Tells the agent to re-read the file if Pint changed it, and reports Pint errors, which usually mean a syntax error. |
| `Stop` | (all) | `scripts/stop-gates.php` | If PHP files have uncommitted changes: runs `vendor/bin/phpstan analyse` (when a PHPStan config exists) and `vendor/bin/pest --dirty`. Failures block the stop and are sent back to the agent. |

A denied edit looks like this to the agent:

```json
{
  "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "deny",
    "permissionDecisionReason": "Blocked by laravel-agent-workflow: .env holds secrets and machine-specific configuration. Do not edit it. Add new keys with safe placeholder values to .env.example and ask the user to update their own .env."
  }
}
```

A failing gate on `Stop` returns `{"decision": "block", "reason": "Quality gates failed (attempt 1 of 3). ..."}` followed by the end of each failing tool's output (at most 60 lines and about 4,000 characters per tool, to stay under Claude Code's 10,000-character limit for hook output).

Settings (environment variables, for example in `.claude/settings.json` under `env`):

| Variable | Default | Effect |
| --- | --- | --- |
| `LARAVEL_GATES_DISABLE` | unset | `1` turns off the `Stop` gates. |
| `LARAVEL_GATES_TEST_COMMAND` | auto | Replaces the detected test command, for example `vendor/bin/pest --parallel` or `./vendor/bin/sail test`. Run through `/bin/sh`. |
| `LARAVEL_GATES_MAX_ATTEMPTS` | `3` | Consecutive times the `Stop` hook may block before it lets the agent stop and shows the remaining failures to you. |
| `LARAVEL_GATES_TIMEOUT` | `240` | Seconds allowed for each gate command. Claude Code cancels the whole `Stop` hook after 600 seconds (set in `hooks/hooks.json`), so keep two gates within that. |
| `LARAVEL_GATES_PHPSTAN_MEMORY` | `1G` | Value for `phpstan --memory-limit`. |
| `LARAVEL_GATES_PINT_TIMEOUT` | `30` | Seconds allowed for Pint on one file. |

### Skills

- `/laravel-agent-workflow:tdd <feature>`: plan examples, write failing Pest tests and confirm they fail for the right reason, implement the smallest change, refactor, then let the gates run.
- `/laravel-agent-workflow:review [base]`: runs both review agents in parallel plus `pint --test`, PHPStan and the tests, and merges the findings into one report with a verdict. It does not edit files.

### Agents

- `laravel-agent-workflow:pr-reviewer`: Laravel conventions, N+1 queries and eager loading, validation via Form Requests, authorization via policies, migrations and test coverage. Based on the [Laravel documentation](https://laravel.com/docs); each check links to the relevant page.
- `laravel-agent-workflow:security-check`: mass assignment, broken access control, SQL injection through raw expressions, XSS through `{!! !!}`, committed secrets, CSRF exclusions, uploads and open redirects. Based on the [OWASP Top 10 (2021)](https://owasp.org/Top10/), the [OWASP API Security Top 10 (2023)](https://owasp.org/API-Security/editions/2023/en/0x11-t10/), the [OWASP Cheat Sheet Series](https://cheatsheetseries.owasp.org/) (including the [Laravel Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Laravel_Cheat_Sheet.html)) and the Laravel security-related docs.

Neither agent has the `Edit` or `Write` tools. Both have `Bash` for `git diff` and similar commands, and their prompts tell them to run only read-only commands.

## How it works

```text
.claude-plugin/plugin.json        plugin manifest
.claude-plugin/marketplace.json   single-plugin marketplace (source "./")
hooks/hooks.json                  PreToolUse / PostToolUse / Stop registrations
scripts/lib.php                   stdin parsing, path checks, process runner
scripts/guard-paths.php           PreToolUse
scripts/format-php.php            PostToolUse
scripts/stop-gates.php            Stop
agents/                           pr-reviewer, security-check
skills/                           tdd, review
templates/CLAUDE.md               project template that defers to Boost
tests/                            PHPUnit tests and tests/smoke/smoke.sh
```

- **Why PHP and not bash + jq:** every Laravel machine has PHP; not every machine has `jq`. PHP also gives reliable JSON handling and runs commands without a shell (`proc_open` with an argument array), so file paths are never shell-interpolated.
- **Hook contract.** Claude Code sends one JSON object on stdin. The scripts always exit `0` and answer with the documented JSON fields: `hookSpecificOutput.permissionDecision` for `PreToolUse`, `hookSpecificOutput.additionalContext` or `decision: "block"` for `PostToolUse`, and `decision: "block"` with a `reason` for `Stop`. A crash therefore shows up as a non-blocking hook error and never as an accidental block. Malformed input is ignored. See the [hooks reference](https://code.claude.com/docs/en/hooks).
- **Project root.** Taken from `CLAUDE_PROJECT_DIR`, then the payload's `cwd`. Paths are normalised lexically, so files that do not exist yet (a `Write`) and `..` segments are handled. Comparisons are case-insensitive to match the default macOS file system.
- **Stop is cheap when nothing changed.** `git status --porcelain` decides whether any `*.php` file has uncommitted changes. If none has, no tools run. PHPStan's result cache keeps repeated runs fast.
- **Loop guard.** Claude Code sets `stop_hook_active` when a stop hook has already continued the conversation. The script counts consecutive blocks per session in `CLAUDE_PLUGIN_DATA` and stops blocking after `LARAVEL_GATES_MAX_ATTEMPTS`, showing the remaining failures to you as a `systemMessage` instead.
- **Tests.** `tests/` runs each script as a separate process with documented payload shapes on stdin, against a temporary project with fake `vendor/bin` tools (shell scripts that log their arguments and simulate passes, reformatting and failures). `tests/smoke/smoke.sh` repeats the important cases against a real `laravel/laravel` install with Pint, Larastan and Pest; the CI workflow runs it on every push to `main` and every pull request.

Run the checks locally:

```bash
composer install
composer check                       # pint --test, phpstan (level max), phpunit
tests/smoke/smoke.sh /tmp/law-smoke  # optional: needs network access to Packagist
claude plugin validate --strict .claude-plugin/plugin.json
```

## Limitations and roadmap

Known limitations in v0.1:

- The path guard covers the `Edit` and `Write` tools only. A `Bash` command such as `echo X >> .env` is not inspected; use Claude Code [permission rules](https://code.claude.com/docs/en/permissions) for that.
- Without Pest, the fallback is `php artisan test` (the full suite), because PHPUnit has no `--dirty` option. Use `LARAVEL_GATES_TEST_COMMAND` to narrow it.
- `pest --dirty` and the "anything changed?" check need a git repository. Outside git, the gates run on every stop and Pest runs the full suite.
- Larastan runs only when `phpstan.neon`, `phpstan.neon.dist` or `phpstan.dist.neon` exists, and analyses the paths from that config, not only the changed files.
- Tools are run from `vendor/bin` on the host. Projects that only run PHP inside Docker or Sail need `php` on the host for the hooks and a custom `LARAVEL_GATES_TEST_COMMAND`. Windows is not tested.
- The hooks were exercised with recorded payloads and in the smoke test, not in a long interactive session. The review agents are prompts: their findings need human judgement.

Possible next steps:

- Guard `Bash` commands that write to protected paths.
- Analyse only changed files with PHPStan as an option for large codebases.
- Optional Rector and `composer audit` gates.
- Per-project configuration file instead of environment variables.

## License

[MIT](LICENSE) © 2026 Oleksii Shaposhnikov
