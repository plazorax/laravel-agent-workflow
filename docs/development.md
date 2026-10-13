# Development

## Layout

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

## Tests

`tests/` runs each script as a separate process with documented payload shapes on stdin, against a temporary project with fake `vendor/bin` tools (shell scripts that log their arguments and simulate passes, reformatting and failures). `tests/smoke/smoke.sh` repeats the important cases against a real `laravel/laravel` install with Pint, Larastan and Pest; the [CI workflow](../.github/workflows/ci.yml) runs it on every push to `main` and every pull request.

Run the checks locally:

```bash
composer install
composer check                       # pint --test, phpstan (level max), phpunit
tests/smoke/smoke.sh /tmp/law-smoke  # optional: needs network access to Packagist
claude plugin validate --strict .claude-plugin/plugin.json
```

## Tested with

PHP 8.4.25, Composer 2.10.3, PHPUnit 11.5.57, PHPStan 2.3.1, Pint 1.32.1, Claude Code 2.1.282 (`claude plugin validate`), git 2.46.0 on macOS. The smoke test ran locally against Laravel 13.35.0 with Larastan 3.13.0 and Pest 5.3.2. The CI workflow is set up to also run the hook tests on PHP 8.2 and 8.3.
