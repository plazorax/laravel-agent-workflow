<?php

declare(strict_types=1);

namespace LaravelAgentWorkflow\Tests;

final class StopGatesTest extends HookTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A minimal "Laravel project": an artisan file and a git repo with one changed PHP file.
        $this->writeFile('artisan', "<?php\n");
        $this->writeFile('phpstan.neon', "parameters:\n    level: 5\n");
        $this->git('init', '-q');
        $this->writeFile('app/Models/Post.php', "<?php\n");
    }

    public function test_it_passes_silently_when_all_gates_pass(): void
    {
        $this->fakeBinary('phpstan');
        $this->fakeBinary('pest');

        $result = $this->runHook('stop-gates.php', $this->payload());

        self::assertSame([0, ''], [$result['exit'], $result['stdout']]);
        self::assertSame(['analyse --no-progress --no-interaction --error-format=raw --memory-limit=1G'], $this->invocations('phpstan'));
        self::assertSame(['--dirty'], $this->invocations('pest'));
    }

    public function test_it_blocks_stopping_and_reports_larastan_errors(): void
    {
        $this->fakeBinary('phpstan', 'echo "app/Models/Post.php:12:Access to an undefined property App\\\\Models\\\\Post::\$titel."; exit 1');
        $this->fakeBinary('pest');

        $result = $this->runHook('stop-gates.php', $this->payload());

        self::assertSame(0, $result['exit']);
        self::assertSame('block', self::field($result, 'decision'));
        $reason = self::field($result, 'reason');
        self::assertStringContainsString('Quality gates failed (attempt 1 of 3)', $reason);
        self::assertStringContainsString('## Larastan (vendor/bin/phpstan analyse)', $reason);
        self::assertStringContainsString('undefined property', $reason);
        self::assertStringNotContainsString('## Tests', $reason);
    }

    public function test_it_blocks_stopping_and_reports_failing_tests(): void
    {
        $this->fakeBinary('phpstan');
        $this->fakeBinary('pest', 'echo "FAILED  Tests\\\\Feature\\\\PostTest > it stores a post"; exit 2');

        $result = $this->runHook('stop-gates.php', $this->payload());

        $reason = self::field($result, 'reason');
        self::assertStringContainsString('## Tests (vendor/bin/pest --dirty)', $reason);
        self::assertStringContainsString('it stores a post', $reason);
    }

    public function test_it_does_nothing_when_no_php_files_changed(): void
    {
        $this->fakeBinary('phpstan', 'exit 1');
        $this->fakeBinary('pest', 'exit 1');
        unlink($this->project.'/app/Models/Post.php');
        $this->writeFile('.gitignore', "/vendor/\n/.plugin-data/\n");
        $this->writeFile('README.md', 'docs only');

        $result = $this->runHook('stop-gates.php', $this->payload());

        self::assertSame([0, ''], [$result['exit'], $result['stdout']]);
        self::assertSame([], $this->invocations('phpstan'));
        self::assertSame([], $this->invocations('pest'));
    }

    public function test_it_does_nothing_outside_a_laravel_project(): void
    {
        unlink($this->project.'/artisan');
        $this->fakeBinary('phpstan', 'exit 1');

        $result = $this->runHook('stop-gates.php', $this->payload());

        self::assertSame([0, ''], [$result['exit'], $result['stdout']]);
        self::assertSame([], $this->invocations('phpstan'));
    }

    public function test_it_skips_larastan_without_a_phpstan_config(): void
    {
        unlink($this->project.'/phpstan.neon');
        $this->fakeBinary('phpstan', 'exit 1');
        $this->fakeBinary('pest');

        $result = $this->runHook('stop-gates.php', $this->payload());

        self::assertSame('', $result['stdout']);
        self::assertSame([], $this->invocations('phpstan'));
        self::assertSame(['--dirty'], $this->invocations('pest'));
    }

    public function test_it_skips_gracefully_when_no_tools_are_installed(): void
    {
        $result = $this->runHook('stop-gates.php', $this->payload());

        self::assertSame([0, ''], [$result['exit'], $result['stdout']]);
    }

    public function test_it_runs_the_full_pest_suite_outside_a_git_repository(): void
    {
        $this->removeGitDirectory();
        $this->fakeBinary('pest');

        $this->runHook('stop-gates.php', $this->payload());

        // The fake logs one line per call; an empty line means "called without arguments".
        self::assertSame("\n", file_get_contents($this->project.'/vendor/bin/pest.log'));
    }

    public function test_it_falls_back_to_artisan_test_when_pest_is_missing(): void
    {
        $this->fakeBinary('phpunit');
        $this->writeFile('artisan', "<?php\nfile_put_contents(__DIR__.'/artisan.log', implode(' ', array_slice(\$argv, 1)));\nexit(1);\n");

        $result = $this->runHook('stop-gates.php', $this->payload());

        self::assertSame('test', file_get_contents($this->project.'/artisan.log'));
        self::assertStringContainsString('## Tests (php artisan test)', self::field($result, 'reason'));
    }

    public function test_a_custom_test_command_overrides_detection(): void
    {
        $this->fakeBinary('pest');

        $result = $this->runHook('stop-gates.php', $this->payload(), ['LARAVEL_GATES_TEST_COMMAND' => 'echo custom-runner-output; exit 3']);

        self::assertSame([], $this->invocations('pest'));
        self::assertStringContainsString('custom-runner-output', self::field($result, 'reason'));
    }

    public function test_it_can_be_disabled_with_an_environment_variable(): void
    {
        $this->fakeBinary('pest', 'exit 1');

        $result = $this->runHook('stop-gates.php', $this->payload(), ['LARAVEL_GATES_DISABLE' => '1']);

        self::assertSame('', $result['stdout']);
        self::assertSame([], $this->invocations('pest'));
    }

    public function test_it_stops_blocking_after_the_maximum_number_of_attempts(): void
    {
        $this->fakeBinary('pest', 'echo still-failing; exit 1');
        $env = ['LARAVEL_GATES_MAX_ATTEMPTS' => '2'];

        $first = $this->runHook('stop-gates.php', $this->payload(stopHookActive: false), $env);
        $second = $this->runHook('stop-gates.php', $this->payload(stopHookActive: true), $env);
        $third = $this->runHook('stop-gates.php', $this->payload(stopHookActive: true), $env);

        self::assertStringContainsString('attempt 1 of 2', self::field($first, 'reason'));
        self::assertStringContainsString('attempt 2 of 2', self::field($second, 'reason'));
        self::assertArrayNotHasKey('decision', $third['json'] ?? []);
        self::assertStringContainsString('still fail after 2 automatic fix attempts', self::field($third, 'systemMessage'));
        self::assertStringContainsString('still-failing', self::field($third, 'systemMessage'));
    }

    public function test_a_new_stop_resets_the_attempt_counter(): void
    {
        $this->fakeBinary('pest', 'exit 1');
        $env = ['LARAVEL_GATES_MAX_ATTEMPTS' => '2'];

        $this->runHook('stop-gates.php', $this->payload(stopHookActive: false), $env);
        $this->runHook('stop-gates.php', $this->payload(stopHookActive: true), $env);
        $fresh = $this->runHook('stop-gates.php', $this->payload(stopHookActive: false), $env);

        self::assertStringContainsString('attempt 1 of 2', self::field($fresh, 'reason'));
    }

    public function test_long_tool_output_is_truncated(): void
    {
        $this->fakeBinary('pest', 'i=1; while [ $i -le 500 ]; do echo "line $i"; i=$((i+1)); done; exit 1');

        $result = $this->runHook('stop-gates.php', $this->payload());

        $reason = self::field($result, 'reason');
        self::assertStringContainsString('[... 440 earlier lines omitted ...]', $reason);
        self::assertStringContainsString('line 500', $reason);
        self::assertStringNotContainsString("line 1\n", $reason);
    }

    public function test_it_still_blocks_when_tool_output_is_not_valid_utf8(): void
    {
        $this->fakeBinary('pest', 'printf "FAILED \\377\\376 bytes\\n"; exit 1');

        $result = $this->runHook('stop-gates.php', $this->payload());

        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('block', self::field($result, 'decision'));
        self::assertStringContainsString('FAILED', self::field($result, 'reason'));
    }

    public function test_the_report_stays_below_the_claude_code_output_cap(): void
    {
        $this->fakeBinary('phpstan', 'i=1; while [ $i -le 300 ]; do printf "%0200d\\n" $i; i=$((i+1)); done; exit 1');
        $this->fakeBinary('pest', 'i=1; while [ $i -le 300 ]; do printf "%0200d\\n" $i; i=$((i+1)); done; exit 1');

        $result = $this->runHook('stop-gates.php', $this->payload());

        self::assertLessThan(10_000, strlen(self::field($result, 'reason')));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(bool $stopHookActive = false): array
    {
        return [
            'session_id' => 'session-1',
            'hook_event_name' => 'Stop',
            'stop_hook_active' => $stopHookActive,
            'last_assistant_message' => 'Done.',
        ];
    }

    private function removeGitDirectory(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->project.'/.git', \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->project.'/.git');
    }
}
