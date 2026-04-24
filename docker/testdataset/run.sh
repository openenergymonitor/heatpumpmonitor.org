#!/bin/bash
# One-shot loader: copy testdataset scripts + extracted phpfina into /tmp, then run upstream PHP scripts.
# Expects /testdataset (read-only repo mount), /bootstrap/scripts-settings.php, shared emon-phpfina volume.

set -euo pipefail

WORKDIR="${TESTDATASET_WORKDIR:-/tmp/testdataset_run}"
export TESTDATASET_ZIP="${TESTDATASET_ZIP:-/testdataset/phpfina.zip}"
ZIP="$TESTDATASET_ZIP"

if [[ ! -f "$ZIP" ]]; then
  echo "ERROR: $ZIP not found (set TESTDATASET_PATH in .env so ../testdataset resolves to the testdataset repo)" >&2
  exit 1
fi

rm -rf "$WORKDIR"
mkdir -p "$WORKDIR"

# emoncms image ships python3 but not necessarily unzip / php-zip
export WORKDIR
python3 - <<'PY'
import os, zipfile, sys
zip_path = os.environ.get("TESTDATASET_ZIP", "/testdataset/phpfina.zip")
dest = os.environ["WORKDIR"]
os.makedirs(dest, exist_ok=True)
try:
    with zipfile.ZipFile(zip_path) as z:
        z.extractall(dest)
except OSError as e:
    print(f"ERROR: extract {zip_path} -> {dest}: {e}", file=sys.stderr)
    sys.exit(1)
PY

if [[ ! -d "$WORKDIR/phpfina" ]]; then
  echo "ERROR: expected $WORKDIR/phpfina after extracting phpfina.zip" >&2
  exit 1
fi

cp -a /testdataset/scripts "$WORKDIR/"
cp /bootstrap/scripts-settings.php "$WORKDIR/scripts/settings.php"

# post_process.php uses include "Modules/postprocess/..." which PHP resolves relative to this script's
# directory (/tmp/.../scripts/), not cwd after load_emoncms.php chdir — use absolute path.
# Upstream postprocess module keeps the model under postprocess-module/ (see emoncms/postprocess readme).
sed -i 's|^include "Modules/postprocess/postprocess_model.php";|include "/var/www/emoncms/Modules/postprocess/postprocess-module/postprocess_model.php";|' \
  "$WORKDIR/scripts/post_process.php"

cd "$WORKDIR"
# Default CLI memory is often 128M; postprocess (powertokwh, battery sim, etc.) needs more on large feeds.
PHP_MEM="${PHP_CLI_MEMORY_LIMIT:-1024M}"
echo "Running add_feeds_to_account.php (WORKDIR=$WORKDIR, memory_limit=$PHP_MEM)..."
php -d "memory_limit=$PHP_MEM" scripts/add_feeds_to_account.php
echo "Running post_process.php..."
php -d "memory_limit=$PHP_MEM" scripts/post_process.php

# Bootstrap the heatpumpmonitor MyHeatpump app + system_meta row against the freshly imported feeds.
if [[ -f /bootstrap/configure_hpm_app.php ]]; then
  echo "Running configure_hpm_app.php..."
  php /bootstrap/configure_hpm_app.php
else
  echo "configure_hpm_app.php not mounted; skipping heatpumpmonitor app wiring"
fi

echo "load_emoncms_testdata: done"
