#!/bin/bash
# Low modulation diagnostics (feed_scan_9.php) for the validated Arotherm+ fleet,
# at the size twins/replay_fleet.py chose (declared, or one size up where upsized),
# so the speed bands match the replay.
#
# Usage, from anywhere: scripts/feed_scan/lowmod_fleet.sh [parallel] [ids...]
#   parallel: scans run at once (default 1); they run under nice, as this is a live server
#   ids: limit to these systems (default all of twins/replay_fleet.csv)
# Output: scripts/feed_scan/lowmod/lowmod_<id>[_q<kW>].json and .txt

cd "$(dirname "$0")/../.."
PAR=${1:-1}
shift
ONLY=" $* "
CSV=analysis/performance_prediction/twins/replay_fleet.csv
OUT=$(pwd)/scripts/feed_scan/lowmod
mkdir -p "$OUT"

PHP="nice -n 10 php -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M"
SCAN="$(pwd)/scripts/feed_scan/feed_scan_9.php"

# one line per scan: system id, second argument (days, or --qnom when upsized), output name
awk -F, 'NR > 1 {
    q = $2 + 0
    if ($33 == "True") print $1, "--qnom=" q, $1 "_q" q
    else print $1, 365, $1
}' "$CSV" | while read id arg name; do
    [ "$ONLY" = "  " ] || [[ "$ONLY" == *" $id "* ]] && echo "$id $arg $name"
done | xargs -n 3 -P "$PAR" sh -c "$PHP $SCAN \$0 \$1 --out=$OUT > $OUT/lowmod_\$2.txt 2>&1; echo \$(date +%H:%M:%S) lowmod_\$2"

n=$(ls "$OUT"/lowmod_*.json 2>/dev/null | wc -l)
echo "done: $n JSON files in $OUT"
grep -L "^Written " "$OUT"/lowmod_*.txt 2>/dev/null | sed "s/^/  failed, see: /"
