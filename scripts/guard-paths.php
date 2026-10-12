<?php

declare(strict_types=1);

/*
 * PreToolUse hook (Edit|Write).
 *
 * Denies edits to .env files, vendor/, storage/ and composer.lock with a reason
 * Claude can act on. Uses the documented PreToolUse JSON output:
 * hookSpecificOutput.permissionDecision = "deny".
 */

namespace LaravelAgentWorkflow;

require __DIR__.'/lib.php';

$input = read_hook_input();
$filePath = tool_file_path($input);

if ($filePath === null) {
    exit(0);
}

$reason = protected_path_reason($filePath, project_dir($input));

if ($reason !== null) {
    emit([
        'hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'deny',
            'permissionDecisionReason' => $reason,
        ],
    ]);
}

exit(0);
