#!/command/with-contenv sh
# Keep in sync with emoncms-docker web/sync_upload-run.sh — mount over image until rebuild.

WWW="${WWW:-/var/www}"
EMONCMS_DIR="${EMONCMS_DIR:-/opt/emoncms}"
DAEMON="${DAEMON:-www-data}"
MODULES_DIR="$WWW/emoncms/Modules"

if [ ! -e "$MODULES_DIR/sync" ] && [ -f "$EMONCMS_DIR/modules/sync/sync_upload.php" ]; then
    echo "sync_upload: linking $MODULES_DIR/sync -> $EMONCMS_DIR/modules/sync"
    ln -snf "$EMONCMS_DIR/modules/sync" "$MODULES_DIR/sync"
fi

if [ ! -f "$MODULES_DIR/sync/sync_model.php" ]; then
    echo "sync_upload: sync module not installed; stopping service"
    s6-svc -O .
    exit 0
fi

cd "$WWW/emoncms" && exec s6-setuidgid "$DAEMON" php Modules/sync/sync_upload.php all bg
