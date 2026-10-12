#!/usr/bin/env bash
#
# End-to-end smoke test: create a fresh Laravel application, install Pint,
# Larastan and Pest, then feed the hook scripts the same JSON Claude Code would
# send and check their decisions against the real tools.
#
# Usage: tests/smoke/smoke.sh [work-dir]
# Requires: php, composer, git, jq and network access to Packagist.

set -euo pipefail

PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORK_DIR="${1:-$(mktemp -d)}"
APP="$WORK_DIR/app"

pass=0
fail() { echo "FAIL: $*" >&2; exit 1; }
ok() { pass=$((pass + 1)); echo "ok $pass - $*"; }

hook() { # hook <script> <json>
    printf '%s' "$2" | CLAUDE_PROJECT_DIR="$APP" CLAUDE_PLUGIN_DATA="$WORK_DIR/plugin-data" php "$PLUGIN_ROOT/scripts/$1"
}

stop_payload='{"session_id":"smoke","hook_event_name":"Stop","stop_hook_active":false}'

echo "# Creating Laravel application in $APP"
composer create-project laravel/laravel "$APP" --no-interaction --prefer-dist --quiet
cd "$APP"

echo "# Installing Larastan and Pest"
composer remove --dev phpunit/phpunit --no-interaction --quiet
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan \
    --with-all-dependencies --no-interaction --quiet
[ -x vendor/bin/pint ] || fail "laravel/laravel no longer ships Pint"

cat > phpstan.neon <<'NEON'
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    paths:
        - app/
    level: 5
NEON

git init -q
git -c user.name=smoke -c user.email=smoke@example.com add -A
git -c user.name=smoke -c user.email=smoke@example.com commit -q -m "Fresh Laravel application"

# --- PreToolUse: protected paths ------------------------------------------------

out="$(hook guard-paths.php "{\"tool_name\":\"Edit\",\"tool_input\":{\"file_path\":\"$APP/.env\"}}")"
[ "$(jq -r '.hookSpecificOutput.permissionDecision' <<<"$out")" = deny ] || fail ".env edit was not denied: $out"
ok "PreToolUse denies .env"

out="$(hook guard-paths.php "{\"tool_name\":\"Write\",\"tool_input\":{\"file_path\":\"$APP/app/Models/User.php\"}}")"
[ -z "$out" ] || fail "app file edit was denied: $out"
ok "PreToolUse allows app/Models/User.php"

# --- PostToolUse: Pint on the edited file ----------------------------------------

mkdir -p app/Support
cat > app/Support/Smoke.php <<'PHP'
<?php
namespace App\Support;
class Smoke {   public function value( ): int { return 1 ;}
}
PHP
out="$(hook format-php.php "{\"tool_name\":\"Write\",\"tool_input\":{\"file_path\":\"$APP/app/Support/Smoke.php\"}}")"
jq -e '.hookSpecificOutput.additionalContext | test("Pint reformatted")' <<<"$out" >/dev/null || fail "Pint did not report a reformat: $out"
vendor/bin/pint --test app/Support/Smoke.php >/dev/null || fail "file is still not Pint-clean"
ok "PostToolUse runs Pint and reports the reformat"

# --- Stop: gates pass -------------------------------------------------------------

out="$(hook stop-gates.php "$stop_payload")"
[ -z "$out" ] || fail "gates should pass on a clean change: $out"
ok "Stop allows finishing when Larastan and pest --dirty pass"

# --- Stop: Larastan failure -------------------------------------------------------

cat > app/Support/Broken.php <<'PHP'
<?php

namespace App\Support;

use App\Models\User;

class Broken
{
    public function name(): string
    {
        return User::query()->firstOrFail()->missingMethod();
    }
}
PHP
out="$(hook stop-gates.php "$stop_payload")"
[ "$(jq -r '.decision' <<<"$out")" = block ] || fail "Larastan error did not block: $out"
jq -e '.reason | test("Larastan")' <<<"$out" >/dev/null || fail "reason does not mention Larastan: $out"
ok "Stop blocks on a Larastan error"
rm app/Support/Broken.php

# --- Stop: failing test -----------------------------------------------------------

cat > tests/Feature/SmokeTest.php <<'PHP'
<?php

it('fails on purpose', function () {
    expect(1)->toBe(2);
});
PHP
out="$(hook stop-gates.php "$stop_payload")"
[ "$(jq -r '.decision' <<<"$out")" = block ] || fail "failing test did not block: $out"
jq -e '.reason | test("pest --dirty")' <<<"$out" >/dev/null || fail "reason does not mention pest --dirty: $out"
ok "Stop blocks on a failing dirty test"

echo "# All $pass smoke checks passed"
