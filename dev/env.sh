#!/usr/bin/env bash
# Shared environment for the local moodle-docker dev site.
export DEV_HOME="$HOME/moodle-dev"
export MOODLE_DOCKER_WWWROOT="$DEV_HOME/moodle"
export MOODLE_DOCKER_DB=pgsql
export MOODLE_DOCKER_PHP_VERSION=8.3
export MOODLE_DOCKER_BROWSER=chrome
export REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [ -d "$MOODLE_DOCKER_WWWROOT/public" ]; then
  export PLUGIN_PATH_IN_MOODLE=public/mod/peerfeedback
else
  export PLUGIN_PATH_IN_MOODLE=mod/peerfeedback
fi
mdc() { "$DEV_HOME/moodle-docker/bin/moodle-docker-compose" "$@"; }
