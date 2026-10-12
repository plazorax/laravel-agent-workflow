---
name: review
description: Review the current Laravel changes with the pr-reviewer and security-check agents and merge their findings into one prioritised report.
argument-hint: "[base branch or commit, default: uncommitted changes]"
disable-model-invocation: true
---

Review the current changes in this Laravel project. Optional base to diff against: **$ARGUMENTS**

## Steps

1. Determine the diff:
   - If a base was given above, use `git diff <base>...HEAD` plus any uncommitted changes.
   - Otherwise use the uncommitted changes (`git diff HEAD`, `git status --porcelain`). If there are none, review the current branch against its merge base with the default branch.
   - If there is nothing to review, say so and stop.
2. Run the two review agents in parallel, giving each the same diff description:
   - `laravel-agent-workflow:pr-reviewer` for conventions, N+1 queries, validation, authorization and tests,
   - `laravel-agent-workflow:security-check` for mass assignment, access control, injection, XSS and secrets.
3. Run the mechanical checks that exist in the project and note their result, without fixing anything:
   - `vendor/bin/pint --test` (or `--dirty --test`),
   - `vendor/bin/phpstan analyse --no-progress` when a PHPStan config exists,
   - `vendor/bin/pest --dirty` or `php artisan test`.
4. Merge the results into one report:
   - remove duplicates (keep the more specific wording),
   - order by severity: blocking or critical first, nits last,
   - keep `path:line` references and the suggested fixes.

## Rules

- This is a review. Do not edit files unless the user asks for fixes after reading the report.
- Do not invent findings. A short "no issues found in X, Y, Z" is a valid result.
- End with a one-line verdict: **ready to merge**, **merge after fixes**, or **needs rework**.
