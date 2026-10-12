<?php

declare(strict_types=1);

/*
 * Stop hook.
 *
 * When Claude is about to finish and PHP files have uncommitted changes, run:
 *   1. Larastan:  vendor/bin/phpstan analyse   (only when a PHPStan config exists)
 *   2. Tests:     vendor/bin/pest --dirty      (Pest, inside a git repository)
 *                 vendor/bin/pest              (Pest, no git repository)
 *                 php artisan test             (no Pest, PHPUnit installed)
 *                 $LARAVEL_GATES_TEST_COMMAND  (override, run through /bin/sh)
 *
 * On failure it returns {"decision":"block","reason":...} so Claude keeps working
 * on the reported problems. To avoid an endless fix loop, it blocks at most
 * LARAVEL_GATES_MAX_ATTEMPTS times in a row (default 3), then lets Claude stop
 * and shows the remaining failures to the user as a systemMessage.
 */

namespace LaravelAgentWorkflow;

require __DIR__.'/lib.php';

/**
 * PHP files with uncommitted changes, or null when git status is unavailable.
 *
 * @return list<string>|null
 */
function changed_php_files(string $root): ?array
{
    $result = run_command(['git', '-C', $root, 'status', '--porcelain', '--untracked-files=all'], $root, 30);
    if ($result['exit'] !== 0) {
        return null;
    }

    $files = [];
    foreach (preg_split('/\r?\n/', $result['output']) ?: [] as $line) {
        if (strlen($line) < 4) {
            continue;
        }
        $path = substr($line, 3);
        if (str_contains($path, ' -> ')) {
            $path = substr($path, strrpos($path, ' -> ') + 4);
        }
        $path = trim($path, '"');
        if (str_ends_with(strtolower($path), '.php')) {
            $files[] = $path;
        }
    }

    return $files;
}

function phpstan_config(string $root): ?string
{
    foreach (['phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon'] as $name) {
        if (is_file($root.'/'.$name)) {
            return $name;
        }
    }

    return null;
}

/**
 * The test command to run and a label for it, or null when no runner is installed.
 *
 * @return array{command: list<string>, label: string}|null
 */
function test_command(string $root, bool $inGitRepo): ?array
{
    $custom = getenv('LARAVEL_GATES_TEST_COMMAND');
    if (is_string($custom) && trim($custom) !== '') {
        return ['command' => ['/bin/sh', '-c', $custom], 'label' => $custom];
    }

    if (is_file($root.'/vendor/bin/pest')) {
        return $inGitRepo
            ? ['command' => [$root.'/vendor/bin/pest', '--dirty'], 'label' => 'vendor/bin/pest --dirty']
            : ['command' => [$root.'/vendor/bin/pest'], 'label' => 'vendor/bin/pest'];
    }

    if (is_file($root.'/vendor/bin/phpunit')) {
        return ['command' => [PHP_BINARY, 'artisan', 'test'], 'label' => 'php artisan test'];
    }

    return null;
}

function state_file(string $root, string $sessionId): string
{
    $dataDir = getenv('CLAUDE_PLUGIN_DATA');
    $dir = is_string($dataDir) && $dataDir !== '' ? $dataDir : sys_get_temp_dir();

    return rtrim($dir, '/').'/stop-gates-'.substr(hash('sha256', $root."\0".$sessionId), 0, 16).'.count';
}

function read_attempts(string $stateFile): int
{
    return is_file($stateFile) ? (int) file_get_contents($stateFile) : 0;
}

function write_attempts(string $stateFile, int $attempts): void
{
    if ($attempts === 0) {
        @unlink($stateFile);

        return;
    }

    if (! is_dir(dirname($stateFile))) {
        @mkdir(dirname($stateFile), 0700, true);
    }
    @file_put_contents($stateFile, (string) $attempts);
}

// ---------------------------------------------------------------------------

$input = read_hook_input();
$root = project_dir($input);

if (getenv('LARAVEL_GATES_DISABLE') === '1' || ! is_file($root.'/artisan')) {
    exit(0);
}

$sessionId = is_string($input['session_id'] ?? null) ? $input['session_id'] : 'no-session';
$stateFile = state_file($root, $sessionId);
$continuing = ($input['stop_hook_active'] ?? false) === true;
$attempts = $continuing ? read_attempts($stateFile) : 0;

$changed = changed_php_files($root);
if ($changed === []) {
    write_attempts($stateFile, 0);
    exit(0);
}

$timeout = env_int('LARAVEL_GATES_TIMEOUT', 240);
$failures = [];

$config = phpstan_config($root);
if ($config !== null && is_file($root.'/vendor/bin/phpstan')) {
    $memory = getenv('LARAVEL_GATES_PHPSTAN_MEMORY') ?: '1G';
    $result = run_command(
        [$root.'/vendor/bin/phpstan', 'analyse', '--no-progress', '--no-interaction', '--error-format=raw', '--memory-limit='.$memory],
        $root,
        $timeout,
    );
    if ($result['exit'] !== 0) {
        $failures['Larastan (vendor/bin/phpstan analyse)'] = tail_output($result['output']);
    }
}

$tests = test_command($root, $changed !== null);
if ($tests !== null) {
    $result = run_command($tests['command'], $root, $timeout);
    if ($result['exit'] !== 0) {
        $failures['Tests ('.$tests['label'].')'] = tail_output($result['output']);
    }
}

if ($failures === []) {
    write_attempts($stateFile, 0);
    exit(0);
}

$report = '';
foreach ($failures as $gate => $output) {
    $report .= "\n\n## {$gate}\n```\n{$output}\n```";
}

$maxAttempts = env_int('LARAVEL_GATES_MAX_ATTEMPTS', 3);
$attempts++;

if ($attempts > $maxAttempts) {
    write_attempts($stateFile, 0);
    emit([
        'systemMessage' => "laravel-agent-workflow: quality gates still fail after {$maxAttempts} automatic fix attempts. "
            .'Review the output below.'.$report,
    ]);
    exit(0);
}

write_attempts($stateFile, $attempts);
emit([
    'decision' => 'block',
    'reason' => "Quality gates failed (attempt {$attempts} of {$maxAttempts}). Fix the problems below and keep "
        .'the change small. Do not weaken tests, add @phpstan-ignore comments or lower the PHPStan level to make '
        .'a gate pass. If a failure is unrelated to your change, say so and stop.'.$report,
]);
exit(0);
