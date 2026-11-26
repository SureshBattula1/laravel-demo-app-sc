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

# Run migrations. Seed only when migrations actually ran (i.e. a fresh database).
MIGRATE_OUTPUT="$(php artisan migrate --force 2>&1)"
echo "$MIGRATE_OUTPUT"
if echo "$MIGRATE_OUTPUT" | grep -q "Nothing to migrate"; then
    echo "Database already migrated - skipping seed."
else
    echo "Fresh database detected - seeding."
    php artisan db:seed --force || true
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

# Serve the API (PHP built-in server is fine for a dev/learning setup).
exec php artisan serve --host=0.0.0.0 --port=8000
