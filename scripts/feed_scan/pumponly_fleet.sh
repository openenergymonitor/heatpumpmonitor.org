#!/bin/bash
# Heat meter dT offset from pump-only running (feed_scan_7.php) for the
# validated Arotherm+ fleet, to compare with the machine replay level.
#
# Usage, from anywhere: scripts/feed_scan/pumponly_fleet.sh [ids_file] [parallel]
#   ids_file: lines of "id kW" (default analysis/performance_prediction/twins/replay_ids.txt)
#   parallel: scans run at once (default 1); they run under nice, as this is a live server
# Output: scripts/feed_scan/pumponly/pumponly_<id>.json and .txt

cd "$(dirname "$0")/../.."
IDS=${1:-analysis/performance_prediction/twins/replay_ids.txt}
PAR=${2:-1}
OUT=$(pwd)/scripts/feed_scan/pumponly
mkdir -p "$OUT"

PHP="nice -n 10 php -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M"
SCAN="$(pwd)/scripts/feed_scan/feed_scan_7.php"

grep -v '^#' "$IDS" | awk '{print $1}' \
    | xargs -n 1 -P "$PAR" sh -c "$PHP $SCAN \$0 365 --out=$OUT > $OUT/pumponly_\$0.txt 2>&1; echo \$(date +%H:%M:%S) pumponly_\$0"

n=$(ls "$OUT"/pumponly_*.json 2>/dev/null | wc -l)
echo "done: $n JSON files in $OUT"
grep -L "^Written " "$OUT"/pumponly_*.txt 2>/dev/null | sed "s/^/  failed, see: /"
