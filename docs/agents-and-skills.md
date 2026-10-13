# Agents and skills

## Skills

- `/laravel-agent-workflow:tdd <feature>` ([source](../skills/tdd/SKILL.md)): plan examples, write failing Pest tests and confirm they fail for the right reason, implement the smallest change, refactor, then let the gates run.
- `/laravel-agent-workflow:review [base]` ([source](../skills/review/SKILL.md)): runs both review agents in parallel plus `pint --test`, PHPStan and the tests, and merges the findings into one report with a verdict. It does not edit files.

## Agents

- `laravel-agent-workflow:pr-reviewer` ([source](../agents/pr-reviewer.md)): Laravel conventions, N+1 queries and eager loading, validation via Form Requests, authorization via policies, migrations and test coverage. Based on the [Laravel documentation](https://laravel.com/docs); each check links to the relevant page.
- `laravel-agent-workflow:security-check` ([source](../agents/security-check.md)): mass assignment, broken access control, SQL injection through raw expressions, XSS through `{!! !!}`, committed secrets, CSRF exclusions, uploads and open redirects. Based on the [OWASP Top 10 (2021)](https://owasp.org/Top10/), the [OWASP API Security Top 10 (2023)](https://owasp.org/API-Security/editions/2023/en/0x11-t10/), the [OWASP Cheat Sheet Series](https://cheatsheetseries.owasp.org/) (including the [Laravel Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Laravel_Cheat_Sheet.html)) and the Laravel security-related docs.

Neither agent has the `Edit` or `Write` tools. Both have `Bash` for `git diff` and similar commands, and their prompts tell them to run only read-only commands.

Both agents, the skills and the [`CLAUDE.md` template](../templates/CLAUDE.md) defer to Laravel Boost's guidelines and `.ai/rules/` when they exist.
