#!/usr/bin/env sh
set -e

if [ -n "${DB_HOST:-}" ]; then
  echo "Waiting for database ${DB_HOST}:${DB_PORT:-3306} ..."
  tries=0
  until php -r '$host=getenv("DB_HOST"); $port=(int)(getenv("DB_PORT") ?: 3306); $fp=@fsockopen($host, $port, $errno, $errstr, 2); if (!$fp) { exit(1); } fclose($fp);'; do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
      echo "Database is still unreachable after 60 attempts."
      exit 1
    fi
    sleep 2
  done
fi

php artisan optimize:clear
php artisan migrate --force

exec "$@"
