#!/usr/bin/env bash
# Run plugin PHPUnit tests in the container. Usage: dev/phpunit.sh <file-in-tests/> | --filter X
set -uo pipefail
source "$(dirname "$0")/env.sh"
stamp="$DEV_HOME/.phpunit-stamp"
hash="$(cat "$REPO_ROOT/version.php" "$REPO_ROOT/db/install.xml" 2>/dev/null | shasum | cut -c1-40)"
if [ ! -f "$stamp" ] || [ "$(cat "$stamp")" != "$hash" ]; then
  mdc exec -T webserver php public/admin/tool/phpunit/cli/init.php >/tmp/pf-phpunit-init.log 2>&1 || { tail -20 /tmp/pf-phpunit-init.log; echo "PHPUNIT: FAIL"; exit 1; }
  echo "$hash" > "$stamp"
fi
if [ "${1:-}" = "--filter" ]; then
  mdc exec -T webserver vendor/bin/phpunit --testsuite mod_peerfeedback_testsuite --filter "$2"
else
  mdc exec -T webserver vendor/bin/phpunit "$PLUGIN_PATH_IN_MOODLE/tests/${1:-}"
fi
rc=$?
[ $rc -eq 0 ] && echo "PHPUNIT: PASS" || echo "PHPUNIT: FAIL"
exit $rc
