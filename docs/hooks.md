# Hooks

The plugin registers three [Claude Code hooks](https://code.claude.com/docs/en/hooks) in [`hooks/hooks.json`](../hooks/hooks.json). They run on every matching tool call, whether or not the model remembers its instructions.

| Event | Matcher | Script | Behaviour |
| --- | --- | --- | --- |
| `PreToolUse` | `Edit\|Write` | [`scripts/guard-paths.php`](../scripts/guard-paths.php) | Denies edits to `.env` and `.env.*` (except `.env.example`), `vendor/`, `storage/` and `composer.lock`, with a reason the agent can act on. |
| `PostToolUse` | `Edit\|Write` | [`scripts/format-php.php`](../scripts/format-php.php) | Runs `vendor/bin/pint <file>` on the edited `*.php` file (not Blade). Tells the agent to re-read the file if Pint changed it, and reports Pint errors, which usually mean a syntax error. |
| `Stop` | (all) | [`scripts/stop-gates.php`](../scripts/stop-gates.php) | If PHP files have uncommitted changes: runs `vendor/bin/phpstan analyse` (when a PHPStan config exists) and `vendor/bin/pest --dirty`. Failures block the stop and are sent back to the agent. |

Each gate is skipped when its tool is not installed. The `Stop` gates also do nothing in a directory without an `artisan` file.

## What the agent sees

A denied edit:

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

## Test command selection

The `Stop` hook picks the first that applies:

1. `LARAVEL_GATES_TEST_COMMAND`, run through `/bin/sh` (see [configuration](configuration.md)).
2. `vendor/bin/pest --dirty` when Pest is installed and the project is a git repository.
3. `vendor/bin/pest` (full suite) when Pest is installed outside git.
4. `php artisan test` when only PHPUnit is installed.

## How it works

- **Why PHP and not bash + jq.** Every Laravel machine has PHP; not every machine has `jq`. PHP also gives reliable JSON handling and runs commands without a shell (`proc_open` with an argument array), so file paths are never shell-interpolated.
- **Hook contract.** Claude Code sends one JSON object on stdin. The scripts always exit `0` and answer with the documented JSON fields: `hookSpecificOutput.permissionDecision` for `PreToolUse`, `hookSpecificOutput.additionalContext` or `decision: "block"` for `PostToolUse`, and `decision: "block"` with a `reason` for `Stop`. A crash therefore shows up as a non-blocking hook error and never as an accidental block. Malformed input is ignored. See the [hooks reference](https://code.claude.com/docs/en/hooks).
- **Project root.** Taken from `CLAUDE_PROJECT_DIR`, then the payload's `cwd`. Paths are normalised lexically, so files that do not exist yet (a `Write`) and `..` segments are handled. Comparisons are case-insensitive to match the default macOS file system.
- **Stop is cheap when nothing changed.** `git status --porcelain` decides whether any `*.php` file has uncommitted changes. If none has, no tools run. PHPStan's result cache keeps repeated runs fast.
- **Loop guard.** Claude Code sets `stop_hook_active` when a stop hook has already continued the conversation. The script counts consecutive blocks per session in `CLAUDE_PLUGIN_DATA` and stops blocking after `LARAVEL_GATES_MAX_ATTEMPTS`, showing the remaining failures to you as a `systemMessage` instead.
