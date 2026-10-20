#!/bin/bash
# Machine replay (feed_scan_6.php) for the validated Arotherm+ fleet. Each system
# runs at its declared capacity and again at the next Arotherm+ size up, since
# some declared sizes are wrong; twins/replay_fleet.py picks between the two.
#
# Usage, from anywhere: scripts/feed_scan/replay_fleet.sh [ids_file] [parallel] [model]
#   ids_file: lines of "id kW" (default analysis/performance_prediction/twins/replay_ids.txt)
#   parallel: scans run at once (default 1); they run under nice, as this is a live server
#   model: datasheet COP model, v2 (default), generic or unit (see feed_scan_6.php)
# Output: scripts/feed_scan/replay/replay_<id>[_q<kW>][_m<model>].json and .txt

cd "$(dirname "$0")/../.."
IDS=${1:-analysis/performance_prediction/twins/replay_ids.txt}
PAR=${2:-1}
MODEL=${3:-v2}
SUF=""
[ "$MODEL" != "v2" ] && SUF="_m$MODEL"
OUT=$(pwd)/scripts/feed_scan/replay
mkdir -p "$OUT"

# PHP's tracing JIT runs the scan about 3x faster with identical output
# (ignored where opcache isn't available)
PHP="nice -n 10 php -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M"
SCAN="$(pwd)/scripts/feed_scan/feed_scan_6.php"

# one line per scan: system id, second argument (days, or the upsized --qnom), output name
grep -v '^#' "$IDS" | while read id kw; do
    case $kw in
        3.5) up=5 ;;
        5) up=7 ;;
        7) up=10 ;;
        10) up=12 ;;
        *) up="" ;;
    esac
    echo "$id 365 $id$SUF"
    [ -n "$up" ] && echo "$id --qnom=$up ${id}_q$up$SUF"
done | xargs -n 3 -P "$PAR" sh -c "$PHP $SCAN \$0 \$1 --model=$MODEL --out=$OUT > $OUT/replay_\$2.txt 2>&1; echo \$(date +%H:%M:%S) replay_\$2"

n=$(ls "$OUT"/replay_*$SUF.json 2>/dev/null | wc -l)
echo "done: $n JSON files in $OUT"
grep -L "^Written " "$OUT"/replay_*.txt 2>/dev/null | sed "s/^/  failed, see: /"
