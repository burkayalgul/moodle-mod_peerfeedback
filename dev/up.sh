#!/usr/bin/env bash
# Start (and if needed create/install/upgrade) the local Moodle 5.2 dev site.
set -euo pipefail
source "$(dirname "$0")/env.sh"
mkdir -p "$DEV_HOME"
[ -d "$DEV_HOME/moodle-docker" ] || git clone -q --depth 1 https://github.com/moodlehq/moodle-docker.git "$DEV_HOME/moodle-docker"
[ -d "$MOODLE_DOCKER_WWWROOT" ] || git clone -q --depth 1 --branch MOODLE_502_STABLE https://github.com/moodle/moodle.git "$MOODLE_DOCKER_WWWROOT"
source "$(dirname "$0")/env.sh"   # re-detect public/ after clone
[ -f "$MOODLE_DOCKER_WWWROOT/config.php" ] || cp "$DEV_HOME/moodle-docker/config.docker-template.php" "$MOODLE_DOCKER_WWWROOT/config.php"
if [ -f "$REPO_ROOT/version.php" ]; then
  sed -e "s#@REPO@#$REPO_ROOT#" -e "s#@PLUGIN@#$PLUGIN_PATH_IN_MOODLE#" "$REPO_ROOT/dev/local.yml.tpl" > "$DEV_HOME/moodle-docker/local.yml"
else
  rm -f "$DEV_HOME/moodle-docker/local.yml"   # an empty plugin dir makes Moodle refuse to install
fi
mdc up -d
"$DEV_HOME/moodle-docker/bin/moodle-docker-wait-for-db"
if mdc exec -T webserver php admin/cli/cfg.php --name=version >/dev/null 2>&1; then
  mdc exec -T webserver php admin/cli/upgrade.php --non-interactive
else
  mdc exec -T webserver php admin/cli/install_database.php --agree-license --fullname="PF dev" \
    --shortname="pfdev" --adminpass="test" --adminemail="admin@example.com"
fi
echo "UP: http://localhost:8000 (admin / test)"
