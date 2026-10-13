# Limitations and roadmap

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
