#!/command/with-contenv sh
# Keep in sync with emoncms-docker web/sql_ready.sh — mounted over the image until a new image is built.

MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_SSL="--skip-ssl"

echo "Waiting for MySQL at $MYSQL_HOST:$MYSQL_PORT (user $MYSQL_USER, db $MYSQL_DATABASE)..."
RETRIES=0
while ! mysql $MYSQL_SSL -h "$MYSQL_HOST" -P "$MYSQL_PORT" --protocol=TCP -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "USE \`$MYSQL_DATABASE\`" 2>/dev/null; do
    RETRIES=$((RETRIES + 1))
    if [ "$RETRIES" -ge 90 ]; then
        echo "ERROR: Could not connect to MySQL after 90 attempts."
        echo "  MYSQL_HOST=$MYSQL_HOST MYSQL_PORT=$MYSQL_PORT MYSQL_USER=$MYSQL_USER MYSQL_DATABASE=$MYSQL_DATABASE"
        echo "If this is a fresh install, ensure the DB container has created this user/database (init SQL / env)."
        echo "Debug (this attempt only):"
        mysql $MYSQL_SSL -h "$MYSQL_HOST" -P "$MYSQL_PORT" --protocol=TCP -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "USE \`$MYSQL_DATABASE\`" 2>&1 || true
        exit 1
    fi
    sleep 1
done
echo "MySQL server is up and credentials OK"

TABLE_EXISTS=$(mysql $MYSQL_SSL -h "$MYSQL_HOST" -P "$MYSQL_PORT" --protocol=TCP -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" \
    -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$MYSQL_DATABASE' AND table_name='users'" 2>/dev/null)

if [ "$TABLE_EXISTS" = "0" ] || [ -z "$TABLE_EXISTS" ]; then
    echo "New install detected - initialising emoncms database schema"
    php "$OEM_DIR/emoncmsdbupdate.php"
else
    echo "Existing emoncms database found"
fi

echo "sql_ready complete, starting workers"
