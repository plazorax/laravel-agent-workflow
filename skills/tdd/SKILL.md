---
name: tdd
description: Build a Laravel feature test-first with Pest - write a failing test, implement the smallest change that passes, then refactor, with Pint, Larastan and the test suite as gates.
argument-hint: <feature description>
disable-model-invocation: true
---

Build this feature test-first: **$ARGUMENTS**

If the feature description is empty or ambiguous, ask one short clarifying question before writing code.

Follow the project's Laravel Boost guidelines and `.ai/rules/` when they exist. Use the Boost `search-docs` tool for version-specific APIs instead of guessing.

## 1. Plan (no code yet)

- Restate the behaviour as 2-5 concrete examples: input, actor, expected result. Include at least one failure case (invalid input, unauthorised user, missing record).
- Name the test file and the test type: a feature test in `tests/Feature/` for anything that goes through HTTP, a console command, a job or Livewire; a unit test in `tests/Unit/` only for logic with no framework dependencies.
- List the production files you expect to touch. Keep the list short.

## 2. Red

- Write the Pest tests for the examples. Use factories, `RefreshDatabase` (or the project's existing trait), `actingAs()` for authentication and `Http::fake()` / `Queue::fake()` / `Mail::fake()` for side effects.
- Run only the new tests: `vendor/bin/pest --filter="<test name>"` (or `php artisan test --filter="<test name>"`).
- Confirm each test fails **for the expected reason**, such as a missing route, a 403 or a wrong value, not a typo or a missing import. Fix the test until it fails for the right reason. Show the failure output.

## 3. Green

- Write the smallest production change that makes the tests pass. Prefer framework features: Form Requests for validation, policies for authorization, Eloquent relationships, resource controllers.
- Re-run the filtered tests until they pass. Do not change test expectations to match the implementation unless the test was wrong; if you change one, say why.

## 4. Refactor

- With the tests green, remove duplication, improve names and move logic to the right layer. Re-run the tests after each step.
- Run the broader suite for the area you touched, for example `vendor/bin/pest tests/Feature/<Area>`.

## 5. Gates

The laravel-agent-workflow hooks run automatically:

- Pint formats each PHP file you write. If it reports that it reformatted a file, re-read that file before editing it again.
- When you finish, the Stop hook runs Larastan and the dirty tests. If it reports failures, fix them; do not add `@phpstan-ignore`, skip tests or lower the PHPStan level to get past a gate.

## 6. Report

Finish with a short summary: the tests added (file and test names), the production files changed, and anything left for the user to decide, such as new `.env` keys (add them to `.env.example`; the hook blocks edits to `.env`) or migrations to run.
