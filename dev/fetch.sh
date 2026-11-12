#!/usr/bin/env bash
# Fetch a page as a given user (password Test123!, admin uses "test"). Usage: dev/fetch.sh <username> <path> [curl args...]
set -euo pipefail
user="$1"; path="$2"; shift 2
pass='Test123!'; [ "$user" = admin ] && pass=test
jar="${TMPDIR:-/tmp}/pf-cookies-$user"
if [ ! -s "$jar" ] || ! curl -s -b "$jar" -o /dev/null -w '%{redirect_url}' http://localhost:8000/my/ | grep -qv login; then
  rm -f "$jar"
  token=$(curl -s -c "$jar" http://localhost:8000/login/index.php | grep -o 'name="logintoken" value="[^"]*"' | sed 's/.*value="//;s/"//')
  curl -s -b "$jar" -c "$jar" -o /dev/null --data-urlencode "username=$user" --data-urlencode "password=$pass" \
    --data-urlencode "logintoken=$token" http://localhost:8000/login/index.php
fi
curl -s -b "$jar" -c "$jar" "$@" "http://localhost:8000$path"
