<?php

declare(strict_types=1);

namespace LaravelAgentWorkflow\Tests;

use PHPUnit\Framework\TestCase;

use function LaravelAgentWorkflow\normalize_path;
use function LaravelAgentWorkflow\read_hook_input;
use function LaravelAgentWorkflow\relative_to;
use function LaravelAgentWorkflow\tail_output;

final class LibTest extends TestCase
{
    public function test_normalize_path_resolves_dot_segments_without_touching_the_disk(): void
    {
        self::assertSame('/app/vendor/x.php', normalize_path('/app/src/../vendor/./x.php', '/'));
        self::assertSame('/project/app/User.php', normalize_path('app/User.php', '/project'));
        self::assertSame('/etc/passwd', normalize_path('../../../etc/passwd', '/project/app'));
    }

    public function test_relative_to_returns_null_outside_the_root(): void
    {
        self::assertSame('app/User.php', relative_to('/project/app/User.php', '/project'));
        self::assertSame('', relative_to('/project', '/project/'));
        self::assertNull(relative_to('/project-other/app/User.php', '/project'));
        self::assertNull(relative_to('/project/../etc/hosts', '/project'));
    }

    public function test_read_hook_input_tolerates_malformed_json(): void
    {
        self::assertSame([], read_hook_input('not json'));
        self::assertSame([], read_hook_input('"a string"'));
        self::assertSame(['tool_name' => 'Edit'], read_hook_input('{"tool_name":"Edit"}'));
    }

    public function test_tail_output_strips_ansi_colours_and_keeps_the_last_lines(): void
    {
        self::assertSame('FAIL red', tail_output("\e[31mFAIL\e[0m red"));
        self::assertSame("[... 2 earlier lines omitted ...]\nc\nd", tail_output("a\nb\nc\nd", 2));
    }

    public function test_tail_output_caps_the_number_of_characters(): void
    {
        $long = str_repeat('x', 30)."\n".str_repeat('y', 30)."\n".str_repeat('z', 30);

        self::assertSame("[... 1 earlier lines omitted ...]\n".str_repeat('y', 30)."\n".str_repeat('z', 30), tail_output($long, 60, 70));
        self::assertSame("[... 2 earlier lines omitted ...]\n...".str_repeat('z', 10), tail_output($long, 60, 10));
    }
}
