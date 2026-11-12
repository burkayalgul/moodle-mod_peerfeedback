#!/usr/bin/env bash
# Run plugin Behat tests in the container. Usage: dev/behat.sh @mod_peerfeedback
set -uo pipefail
source "$(dirname "$0")/env.sh"
stamp="$DEV_HOME/.behat-stamp"
hash="$(find "$REPO_ROOT" -path "$REPO_ROOT/.git" -prune -o -path "$REPO_ROOT/.superpowers" -prune -o -type f \( -name '*.php' -o -name '*.xml' -o -name '*.css' -o -name '*.mustache' \) -print0 | sort -z | xargs -0 cat | shasum | cut -c1-40)"
if [ ! -f "$stamp" ] || [ "$(cat "$stamp")" != "$hash" ]; then
  mdc exec -T webserver php public/admin/tool/behat/cli/init.php >/tmp/pf-behat-init.log 2>&1 || { tail -20 /tmp/pf-behat-init.log; echo "BEHAT: FAIL"; exit 1; }
  echo "$hash" > "$stamp"
fi
mdc exec -T -u www-data webserver php public/admin/tool/behat/cli/run.php --tags="${1:-@mod_peerfeedback}"
rc=$?
[ $rc -eq 0 ] && echo "BEHAT: PASS" || echo "BEHAT: FAIL"
exit $rc
