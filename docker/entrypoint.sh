#!/bin/sh
set -e

cd /var/www/html

# Provide a container .env on first boot.
if [ ! -f .env ]; then
    cp .env.docker .env
fi

# Laravel reads .env; Compose injects DB_* — always mirror Docker DB settings into .env.
if [ -n "${DB_HOST:-}" ]; then
    for key in DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
        eval "val=\${$key:-}"
        [ -n "$val" ] || continue
        if grep -q "^${key}=" .env 2>/dev/null; then
            sed -i "s|^${key}=.*|${key}=${val}|" .env
        else
            echo "${key}=${val}" >> .env
        fi
    done
fi

# Ensure an application key exists.
if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --force
fi

# Wait for the MySQL service to accept connections.
echo "Waiting for database at ${DB_HOST}:${DB_PORT} ..."
until php -r '
    try {
        new PDO(
            "mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
' 2>/dev/null; do
    sleep 2
done
echo "Database is up."

# Seed only on a database that has never been migrated. A later migration must not reseed.
MIGRATION_ROWS="$(php -r '
    try {
        $pdo = new PDO(
            "mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
        $count = $pdo->query("SELECT COUNT(*) FROM migrations")->fetchColumn();
        echo (int) $count;
    } catch (Throwable $e) {
        echo "0";
    }
' 2>/dev/null || echo 0)"
php artisan migrate --force
if [ "${MIGRATION_ROWS:-0}" = "0" ]; then
    echo "Fresh database detected - seeding."
    php artisan db:seed --force || true
else
    echo "Existing database - skipping seed."
fi

php artisan storage:link --force 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

if [ -n "${APP_URL:-}" ]; then
    if grep -q "^APP_URL=" .env 2>/dev/null; then
        sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
    else
        echo "APP_URL=${APP_URL}" >> .env
    fi
fi

php artisan config:clear

if [ "${1:-serve}" = "queue" ]; then
    exec php artisan queue:work database --queue=campaigns,campaigns-materialize,campaigns-send --sleep=1 --tries=3 --timeout=120
fi

# Serve the API (PHP built-in server is fine for a dev/learning setup).
exec php artisan serve --host=0.0.0.0 --port=8000
