<?php

declare(strict_types=1);

namespace LaravelAgentWorkflow\Tests;

final class FormatPhpTest extends HookTestCase
{
    public function test_it_runs_pint_on_the_edited_file_only(): void
    {
        $this->fakeBinary('pint');
        $file = $this->writeFile('app/Models/Post.php', "<?php\n");

        $result = $this->runHook('format-php.php', $this->payload($file));

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stdout'], 'No output when Pint leaves the file unchanged');
        self::assertSame([$file], $this->invocations('pint'));
    }

    public function test_it_tells_claude_to_reread_a_reformatted_file(): void
    {
        $this->fakeBinary('pint', 'printf "<?php\n\n// formatted\n" > "$1"');
        $file = $this->writeFile('app/Models/Post.php', "<?php\n");

        $result = $this->runHook('format-php.php', $this->payload($file));

        self::assertSame(0, $result['exit']);
        self::assertSame('PostToolUse', self::field($result, 'hookSpecificOutput', 'hookEventName'));
        self::assertStringContainsString(
            'Pint reformatted app/Models/Post.php. Re-read the file',
            self::field($result, 'hookSpecificOutput', 'additionalContext'),
        );
    }

    public function test_it_reports_pint_failures_back_to_claude(): void
    {
        $this->fakeBinary('pint', 'echo "PHP Parse error: syntax error, unexpected end of file"; exit 1');
        $file = $this->writeFile('app/Broken.php', "<?php\nfunction (\n");

        $result = $this->runHook('format-php.php', $this->payload($file));

        self::assertSame(0, $result['exit']);
        self::assertSame('block', self::field($result, 'decision'));
        self::assertStringContainsString('Pint could not format app/Broken.php (exit 1)', self::field($result, 'reason'));
        self::assertStringContainsString('syntax error', self::field($result, 'reason'));
    }

    public function test_it_skips_non_php_and_blade_files(): void
    {
        $this->fakeBinary('pint');
        $js = $this->writeFile('resources/js/app.js', 'x');
        $blade = $this->writeFile('resources/views/welcome.blade.php', '<div></div>');

        $this->runHook('format-php.php', $this->payload($js));
        $this->runHook('format-php.php', $this->payload($blade));

        self::assertSame([], $this->invocations('pint'));
    }

    public function test_it_skips_gracefully_when_pint_is_not_installed(): void
    {
        $file = $this->writeFile('app/Models/Post.php', "<?php\n");

        $result = $this->runHook('format-php.php', $this->payload($file));

        self::assertSame([0, ''], [$result['exit'], $result['stdout']]);
    }

    public function test_it_skips_files_that_do_not_exist_or_are_outside_the_project(): void
    {
        $this->fakeBinary('pint');

        $this->runHook('format-php.php', $this->payload($this->project.'/app/Missing.php'));
        $this->runHook('format-php.php', $this->payload(__FILE__));

        self::assertSame([], $this->invocations('pint'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $filePath): array
    {
        return [
            'session_id' => 'test',
            'hook_event_name' => 'PostToolUse',
            'tool_name' => 'Write',
            'tool_input' => ['file_path' => $filePath, 'content' => '<?php'],
            'tool_response' => ['filePath' => $filePath, 'type' => 'create'],
        ];
    }
}
