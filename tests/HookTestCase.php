<?php

declare(strict_types=1);

namespace LaravelAgentWorkflow\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Runs hook scripts as Claude Code does: a separate process, JSON on stdin,
 * exit code and stdout inspected afterwards. Each test gets a throwaway
 * "project" directory with fake vendor/bin tools.
 */
abstract class HookTestCase extends TestCase
{
    protected string $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = sys_get_temp_dir().'/law-test-'.bin2hex(random_bytes(6));
        mkdir($this->project, 0777, true);
        // Resolve symlinks (macOS /var -> /private/var) so paths compare equal.
        $this->project = (string) realpath($this->project);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->project);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $env
     * @return array{exit: int, stdout: string, stderr: string, json: array<mixed>|null}
     */
    protected function runHook(string $script, array $input, array $env = []): array
    {
        $baseEnv = getenv();
        unset($baseEnv['CLAUDE_PROJECT_DIR'], $baseEnv['CLAUDE_PLUGIN_DATA'], $baseEnv['LARAVEL_GATES_TEST_COMMAND']);
        $env = array_merge($baseEnv, ['CLAUDE_PROJECT_DIR' => $this->project, 'CLAUDE_PLUGIN_DATA' => $this->project.'/.plugin-data'], $env);

        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__).'/scripts/'.$script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->project,
            $env,
        );
        self::assertIsResource($process);

        fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        $json = null;
        if (trim($stdout) !== '') {
            $decoded = json_decode($stdout, true);
            $json = is_array($decoded) ? $decoded : null;
            self::assertNotNull($json, "Hook stdout is not a JSON object:\n".$stdout);
        }

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr, 'json' => $json];
    }

    /**
     * A string value from the hook's JSON output, or '' when it is missing.
     *
     * @param  array{json: array<mixed>|null}  $result
     */
    protected static function field(array $result, string ...$path): string
    {
        $value = $result['json'];
        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return '';
            }
            $value = $value[$key];
        }

        return is_string($value) ? $value : '';
    }

    /**
     * Create an executable fake tool in vendor/bin that logs its arguments
     * to vendor/bin/<name>.log and then runs $body (POSIX sh).
     */
    protected function fakeBinary(string $name, string $body = 'exit 0'): void
    {
        $dir = $this->project.'/vendor/bin';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/'.$name;
        file_put_contents($path, "#!/bin/sh\necho \"\$@\" >> \"\$0.log\"\n".$body."\n");
        chmod($path, 0755);
    }

    /**
     * @return list<string> one entry per invocation
     */
    protected function invocations(string $name): array
    {
        $log = $this->project.'/vendor/bin/'.$name.'.log';

        return is_file($log) ? array_values(array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    }

    protected function writeFile(string $relative, string $contents = ''): string
    {
        $path = $this->project.'/'.$relative;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);

        return $path;
    }

    protected function git(string ...$args): void
    {
        $command = ['git', '-C', $this->project, ...array_values($args)];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), 'git '.implode(' ', $args).' failed');
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
