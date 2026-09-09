#!/usr/bin/env bash
# =============================================================================
# The TOEFL House — retention helper (sourced, not executed)
# =============================================================================
# Shared "keep the newest N entries, delete the rest" rule used by
# deploy/backup.sh (dump retention) and deploy/deploy.sh (old releases).
#
# It exists as a function because the naive pipeline was wrong under this
# repository's `set -euo pipefail`:
#
#   ls -1t "$DIR/${DB_NAME}-"*.dump.age | tail -n +15 | xargs -r rm -f
#
# `ls` exits 2 when its glob matches nothing — completely normal for a
# not-yet-aged backup directory — and pipefail carries that status out of the
# pipeline. A verified pg_dump was therefore reported as a failed backup, and a
# healthy deployment was reported as failed while pruning releases after
# go-live. "Nothing to prune" is not an error, so it is handled here, in one
# place, with a test.
# =============================================================================

# prune_old_entries <dir> <glob> <keep> [delete-command]
#
# `<glob>` is expanded inside `<dir>` (so the names stay usable without any
# path/basename guessing), the newest `<keep>` entries survive, and everything
# older is removed with `<delete-command>` (default `rm -f`; pass `rm -rf` for
# directories). Returns 0 whenever there is nothing to do, including when the
# glob matches no file at all.
prune_old_entries() {
    local dir="$1" glob="$2" keep="$3" delete="${4:-rm -f}"
    local listing

    [ -d "$dir" ] || return 0

    # The glob is deliberately unquoted: it must expand here, inside $dir.
    # -d is required, not cosmetic: without it `ls -1t *` treats each matched
    # directory as a container and prints its *contents*, which would make the
    # release-pruning call delete files inside releases instead of old releases.
    if ! listing="$(cd "$dir" && ls -1dt $glob 2>/dev/null)"; then
        return 0
    fi
    [ -n "$listing" ] || return 0

    printf '%s\n' "$listing" | tail -n +$((keep + 1)) | while IFS= read -r stale; do
        [ -n "$stale" ] || continue
        # Words in $delete are intentional (it is a command, not data); the
        # filename itself stays quoted.
        # shellcheck disable=SC2086
        ( cd "$dir" && $delete -- "$stale" )
    done
}
