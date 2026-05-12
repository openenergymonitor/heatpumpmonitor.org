#!/bin/bash
# One-shot loader: copy testdataset scripts + extracted phpfina into /tmp, then run upstream PHP scripts.
# Expects /testdataset (read-only repo mount), /bootstrap/scripts-settings.php, shared emon-phpfina volume.

set -euo pipefail

WORKDIR="${TESTDATASET_WORKDIR:-/tmp/testdataset_run}"
export TESTDATASET_ZIP="${TESTDATASET_ZIP:-/testdataset/phpfina.zip}"
ZIP="$TESTDATASET_ZIP"

# Idempotency guard: if the target user already has feeds AND a myheatpump app in the emoncms DB
# we treat the testdata as already loaded and skip the (slow) phpfina extract + postprocess.
# Set TESTDATASET_FORCE=1 to re-run anyway (warning: add_feeds_to_account.php may duplicate feeds —
# `docker compose down -v` first if you want a truly clean slate).
FORCE="${TESTDATASET_FORCE:-0}"
if [[ "$FORCE" != "1" && "$FORCE" != "true" ]]; then
  if php /bootstrap/check_loaded.php; then
    echo "load_emoncms_testdata: already loaded (set TESTDATASET_FORCE=1 to re-run); skipping."
    exit 0
  fi
fi

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

# add_feeds_to_account.php and configure_hpm_app.php both look up TESTDATASET_EMONCMS_USER_ID in
# emoncms's `users` table. dev_env/load_dev_env_data.php normally seeds that, but it now runs
# AFTER us — so create the admin user up-front. load_dev_env_data's SELECT-by-username guard then
# leaves this row alone, preserving the apikeys configure_hpm_app.php depends on.
echo "Running bootstrap_admin.php..."
php /bootstrap/bootstrap_admin.php

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
