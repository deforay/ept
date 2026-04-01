#!/bin/bash
set -e

# Generate configs from dist templates if they don't exist
CONFIG_DIR="/var/www/ept/application/configs"

if [ ! -f "$CONFIG_DIR/application.ini" ]; then
    cp "$CONFIG_DIR/application.dist.ini" "$CONFIG_DIR/application.ini"

    # Apply environment variables to application.ini
    sed -i "s|^resources.db.params.host\s*=.*|resources.db.params.host = ${DB_HOST:-db}|" "$CONFIG_DIR/application.ini"
    sed -i "s|^resources.db.params.username\s*=.*|resources.db.params.username = ${DB_USER:-root}|" "$CONFIG_DIR/application.ini"
    sed -i "s|^resources.db.params.password\s*=.*|resources.db.params.password = ${DB_PASSWORD:-ept_secret}|" "$CONFIG_DIR/application.ini"
    sed -i "s|^resources.db.params.dbname\s*=.*|resources.db.params.dbname = ${DB_NAME:-ept}|" "$CONFIG_DIR/application.ini"
    sed -i "s|^domain\s*=.*|domain = ${APP_DOMAIN:-http://localhost/}|" "$CONFIG_DIR/application.ini"

    # Generate security salt
    SALT=$(openssl rand -hex 32)
    sed -i "s|^security.salt\s*=.*|security.salt = '${SALT}'|" "$CONFIG_DIR/application.ini"

    echo "Generated application.ini from template."
fi

if [ ! -f "$CONFIG_DIR/config.ini" ] && [ -f "$CONFIG_DIR/config.dist.ini" ]; then
    cp "$CONFIG_DIR/config.dist.ini" "$CONFIG_DIR/config.ini"
    echo "Generated config.ini from template."
fi

if [ ! -f "$CONFIG_DIR/.env" ]; then
    echo "FORM_SECRET=$(openssl rand -hex 32)" > "$CONFIG_DIR/.env"
    echo "Generated .env with FORM_SECRET."
fi

# Wait for MySQL to be ready
echo "Waiting for MySQL..."
max_tries=30
count=0
until php -r "new PDO('mysql:host=${DB_HOST:-db};port=3306', '${DB_USER:-root}', '${DB_PASSWORD:-ept_secret}');" 2>/dev/null; do
    count=$((count + 1))
    if [ $count -ge $max_tries ]; then
        echo "MySQL not reachable after ${max_tries} attempts. Starting anyway..."
        break
    fi
    sleep 2
done
echo "MySQL is ready."

# Run migrations
echo "Running database migrations..."
cd /var/www/ept
composer post-update --no-interaction 2>&1 || echo "Migration warning (may be first run)."

# Run one-time scripts
php bin/run-once.php 2>/dev/null || true

# Ensure permissions
chown -R www-data:www-data application/cache logs downloads backups public/temporary public/uploads

# Start cron
cron

exec "$@"
