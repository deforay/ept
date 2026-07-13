#!/usr/bin/env bash
#
# CI hygiene backstop — server-side twin of bin/hooks/pre-commit, for changes
# that reach the remote regardless of whether the local hook ran (--no-verify,
# hook never installed, etc.). Scans only what the given commit range INTRODUCES:
#
#   1. Merge-conflict markers — git's own detector over the range (any file type).
#   2. Debug leftovers — die / var_dump / dd / dump on ADDED PHP lines only, so
#      the codebase's existing legit die()/exit is never flagged. Skips `exit`,
#      comments, and the `... or die("msg")` idiom (kept in lockstep with the hook).
#
# Usage:  bin/ci/hygiene.sh <base-ref> [head-ref]     (head defaults to HEAD)
# Diffs from the merge-base of base/head so it works for both PR branches and
# straight-line pushes. Emits GitHub Actions ::error annotations; exits 1 on any
# finding, 0 when clean.
set -uo pipefail

base="${1:?usage: hygiene.sh <base-ref> [head-ref]}"
head="${2:-HEAD}"

# merge-base keeps the range to what THIS branch/push added (not changes that
# landed on base meanwhile); fall back to the raw base if no common ancestor.
range_base="$(git merge-base "$base" "$head" 2>/dev/null || echo "$base")"

status=0

# --- 1: merge-conflict markers (all file types) ---------------------------
conflicts="$(git diff --check "$range_base" "$head" 2>/dev/null | grep -i 'conflict marker' || true)"
if [ -n "$conflicts" ]; then
    echo "::group::Merge-conflict markers"
    while IFS= read -r line; do
        file="${line%%:*}"
        echo "::error file=${file}::${line}"
    done <<< "$conflicts"
    echo "::endgroup::"
    status=1
fi

# --- 2: debug leftovers on added PHP lines --------------------------------
# Regex kept identical to bin/hooks/pre-commit; leading [^>_:a-zA-Z] rejects
# method/member calls (->dump, ::dump) and identifiers ending in a token.
debug_re='(^|[^>_:a-zA-Z])(die[[:space:]]*[(;]|var_dump[[:space:]]*\(|dd[[:space:]]*\(|dump[[:space:]]*\()'
while IFS= read -r -d '' f; do
    while IFS= read -r line; do
        content="${line#+}"
        trimmed="${content#"${content%%[![:space:]]*}"}"
        case "$trimmed" in
            //*|'#'*|'*'*|/\**) continue ;;   # comment lines
        esac
        case "$content" in
            *'or die'*) continue ;;           # legacy `... or die("msg")` idiom
        esac
        if printf '%s\n' "$content" | grep -qE "$debug_re"; then
            echo "::error file=${f}::debug leftover: ${trimmed}"
            status=1
        fi
    done < <(git diff "$range_base" "$head" -U0 --diff-filter=ACM -- "$f" | grep -E '^\+[^+]')
done < <(git diff "$range_base" "$head" --name-only --diff-filter=ACM -z -- '*.php')

if [ "$status" -ne 0 ]; then
    echo "✋ Hygiene check failed — see annotations above." >&2
fi
exit "$status"
