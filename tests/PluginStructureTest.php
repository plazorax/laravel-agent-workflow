<?php

declare(strict_types=1);

namespace LaravelAgentWorkflow\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Cheap consistency checks so a renamed script or a broken manifest fails CI.
 * `claude plugin validate` is the authoritative check; it runs separately.
 */
final class PluginStructureTest extends TestCase
{
    private const ROOT = __DIR__.'/..';

    public function test_manifest_and_composer_versions_match_the_changelog(): void
    {
        $manifest = $this->json('.claude-plugin/plugin.json');
        $changelog = (string) file_get_contents(self::ROOT.'/CHANGELOG.md');

        self::assertSame('laravel-agent-workflow', $manifest['name']);
        self::assertIsString($manifest['version']);
        self::assertStringContainsString('## ['.$manifest['version'].']', $changelog);
    }

    public function test_every_hook_command_points_to_an_existing_script(): void
    {
        $hooks = $this->json('hooks/hooks.json');
        self::assertIsArray($hooks['hooks']);

        $scripts = [];
        foreach ($hooks['hooks'] as $event => $matchers) {
            self::assertContains($event, ['PreToolUse', 'PostToolUse', 'Stop']);
            self::assertIsArray($matchers);
            foreach ($matchers as $matcher) {
                self::assertIsArray($matcher);
                self::assertIsArray($matcher['hooks']);
                foreach ($matcher['hooks'] as $hook) {
                    self::assertIsArray($hook);
                    self::assertSame('command', $hook['type']);
                    self::assertIsString($hook['command']);
                    self::assertMatchesRegularExpression('#^php "\$\{CLAUDE_PLUGIN_ROOT\}/scripts/[a-z-]+\.php"$#', $hook['command']);
                    $scripts[] = basename(rtrim($hook['command'], '"'));
                }
            }
        }

        self::assertSame(['guard-paths.php', 'format-php.php', 'stop-gates.php'], $scripts);
        foreach ($scripts as $script) {
            self::assertFileExists(self::ROOT.'/scripts/'.$script);
        }
    }

    public function test_agents_and_skills_have_name_and_description_front_matter(): void
    {
        $files = array_merge(glob(self::ROOT.'/agents/*.md') ?: [], glob(self::ROOT.'/skills/*/SKILL.md') ?: []);
        self::assertCount(4, $files);

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);
            self::assertMatchesRegularExpression('/\A---\nname: [a-z-]+\ndescription: .{20,}\n/', $contents, $file);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $relative): array
    {
        $decoded = json_decode((string) file_get_contents(self::ROOT.'/'.$relative), true, 64, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
