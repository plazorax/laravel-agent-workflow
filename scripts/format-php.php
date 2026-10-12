<?php

declare(strict_types=1);

/*
 * PostToolUse hook (Edit|Write).
 *
 * Runs Laravel Pint on the one PHP file that was just written, when the project
 * has vendor/bin/pint. Blade templates are skipped because Pint does not format them.
 *
 * - file reformatted -> additionalContext tells Claude to re-read before the next Edit
 * - Pint failed      -> decision "block" so the error is shown next to the tool result
 * - nothing to do    -> silent exit 0
 */

namespace LaravelAgentWorkflow;

require __DIR__.'/lib.php';

$input = read_hook_input();
$filePath = tool_file_path($input);

if ($filePath === null || ! str_ends_with(strtolower($filePath), '.php') || str_ends_with(strtolower($filePath), '.blade.php')) {
    exit(0);
}

$root = project_dir($input);
$pint = $root.'/vendor/bin/pint';
$file = normalize_path($filePath, $root);

if (! is_file($pint) || ! is_file($file) || relative_to($file, $root) === null) {
    exit(0);
}

$before = (string) hash_file('sha256', $file);
$result = run_command([$pint, $file], $root, env_int('LARAVEL_GATES_PINT_TIMEOUT', 30));
clearstatcache(true, $file);
$relative = (string) relative_to($file, $root);

if ($result['exit'] !== 0) {
    emit([
        'decision' => 'block',
        'reason' => "Pint could not format {$relative} (exit {$result['exit']}). This usually means a PHP syntax error. "
            ."Fix it before continuing.\n\n".tail_output($result['output'], 30),
    ]);
    exit(0);
}

if ((string) hash_file('sha256', $file) !== $before) {
    emit([
        'hookSpecificOutput' => [
            'hookEventName' => 'PostToolUse',
            'additionalContext' => "Pint reformatted {$relative}. Re-read the file before editing it again so "
                .'old_string matches the formatted code.',
        ],
    ]);
}

exit(0);
