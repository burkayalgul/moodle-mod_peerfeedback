#!/usr/bin/env bash
# Build dist/mod_peerfeedback-<release>.zip with top folder peerfeedback/, ready for "Install plugins".
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
release="$(sed -n "s/.*release *= *'\([^']*\)'.*/\1/p" "$root/version.php")"
out="$root/dist/mod_peerfeedback-$release.zip"
stage="$(mktemp -d "${TMPDIR:-/tmp}/pfzip.XXXXXX")"
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/peerfeedback" "$root/dist"
rsync -a --exclude='/dev/' --exclude='/tools/' --exclude='/docs/' --exclude='.git*' --exclude='/.superpowers/' \
  --exclude='/dist/' --exclude='.DS_Store' --exclude='/.impeccable/' "$root/" "$stage/peerfeedback/"
rm -f "$out"
(cd "$stage" && zip -qr "$out" peerfeedback)
echo "$out"
