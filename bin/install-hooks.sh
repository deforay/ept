#!/usr/bin/env bash
#
# Install repo git hooks by symlinking the real .git/hooks/<name> -> bin/hooks/<name>.
# Run once per clone:  composer install-hooks   (or: bash bin/install-hooks.sh)
# Idempotent and safe to re-run; no-op outside a git checkout (e.g. tarball deploy).
#
# NOTE: targets `git rev-parse --git-common-dir`/hooks (the literal repo hooks dir),
# NOT `--git-path hooks` -- the latter honors core.hooksPath and would write into a
# globally-configured hooks dir instead of this repo's.
set -euo pipefail

root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    echo "ℹ install-hooks: not a git checkout — skipping."
    exit 0
}
cd "$root"
hooks_dir="$(git rev-parse --git-common-dir)/hooks"
mkdir -p "$hooks_dir"

for src in bin/hooks/*; do
    [ -e "$src" ] || continue
    name="$(basename "$src")"
    chmod +x "$src"
    ln -sf "$root/bin/hooks/$name" "$hooks_dir/$name"
    echo "✓ installed $name -> $hooks_dir/$name"
done
