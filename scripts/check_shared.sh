#!/bin/sh
#
# Check the files shared with emoncms.org for drift.
#
# HeatpumpMonitor has no users table of its own: it reads and writes emoncms's.
# A few files therefore have to stay identical in both codebases or users get
# locked out of one of the two sites. See www/Lib/SHARED.md for what they are
# and why.
#
# Usage: scripts/check_shared.sh [path-to-emoncms]
#
# Exits non zero if any shared file differs, so it can be used as a commit hook
# or a CI step.

set -eu

HERE=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
OTHER=${1:-/var/www/emoncmsorg-main}

if [ ! -d "$OTHER" ]; then
    echo "emoncms checkout not found at: $OTHER" >&2
    echo "Usage: $0 [path-to-emoncms]" >&2
    exit 2
fi

# "<file here>|<file there>"
FILES="
www/Lib/password.php|Lib/password.php
www/Lib/EmonLogger.php|Lib/EmonLogger.php
www/Modules/user/rememberme_model.php|Modules/user/rememberme_model.php
"

status=0

for pair in $FILES; do
    mine=$HERE/${pair%%|*}
    theirs=$OTHER/${pair##*|}

    if [ ! -f "$mine" ]; then
        echo "MISSING  $mine"
        status=1
    elif [ ! -f "$theirs" ]; then
        echo "MISSING  $theirs"
        status=1
    elif ! diff -q "$mine" "$theirs" >/dev/null 2>&1; then
        echo "DRIFTED  ${pair%%|*}"
        diff -u "$mine" "$theirs" | sed 's/^/         /'
        status=1
    else
        echo "ok       ${pair%%|*}"
    fi
done

if [ $status -ne 0 ]; then
    echo
    echo "Shared files have drifted. Copy the intended version to the other" >&2
    echo "repository and commit both. See www/Lib/SHARED.md." >&2
fi

exit $status
