#!/usr/bin/env bash
# Smoke test: real WordPress + MariaDB in Docker, plugin mounted read-only.
# Usage: tests/smoke.sh [wp-image-tag]   (default 6.8-php8.3-apache)
set -euo pipefail
cd "$(dirname "$0")/.."
TAG="${1:-6.8-php8.3-apache}"
CLI_TAG="${CLI_TAG:-cli-php8.3}"
P="seqwp-smoke-$$"
NET="$P-net"
cleanup() { docker rm -f "$P-db" "$P-web" >/dev/null 2>&1 || true; docker volume rm "$P-html" >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; }
trap cleanup EXIT

# --internal: no internet from the test stack (no core/theme auto-updates, no phone-home mid-test).
docker network create --internal "$NET" >/dev/null
docker volume create "$P-html" >/dev/null
docker run -d --name "$P-db" --network "$NET" --network-alias db \
  -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wp -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp \
  mariadb:11 >/dev/null
docker run -d --name "$P-web" --network "$NET" --network-alias web \
  -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp \
  -e WORDPRESS_DEBUG=1 \
  -e WORDPRESS_CONFIG_EXTRA="define( 'AUTOMATIC_UPDATER_DISABLED', true ); define( 'DISABLE_WP_CRON', true ); define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false );" \
  -v "$P-html":/var/www/html \
  -v "$PWD/sequential-analytics":/var/www/html/wp-content/plugins/sequential-analytics:ro \
  -v "$PWD/tests":/tests:ro \
  "wordpress:$TAG" >/dev/null

wp() { docker run --rm --network "$NET" --volumes-from "$P-web" --user 33:33 -e HOME=/tmp \
  -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp \
  "wordpress:$CLI_TAG" wp "$@"; }

for i in $(seq 1 60); do
  if docker exec "$P-web" test -f /var/www/html/wp-config.php && wp db check >/dev/null 2>&1; then break; fi
  sleep 2
done
wp core install --url=http://web --title=Smoke --admin_user=admin --admin_password=admin-pass --admin_email=a@example.invalid --skip-email >/dev/null
wp user create sub s@example.invalid --role=subscriber --user_pass=sub-pass >/dev/null
echo "WordPress $(wp core version), PHP $(docker exec "$P-web" php -r 'echo PHP_VERSION;')"
wp plugin activate sequential-analytics
wp plugin list --name=sequential-analytics --fields=name,status,version
status=0
fail() { echo "FAIL $*"; status=1; }

wp eval-file /tests/smoke.php || fail "smoke.php reported failures"

# First save with a bad key and host: exactly the two real errors, no spurious "key missing".
ds="$(wp eval-file /tests/double-sanitize.php || true)"
echo "$ds"
errs="$(printf '%s\n' "$ds" | { grep '^error: ' || true; } | sort | tr '\n' ' ')"
[ "$errs" = "error: sequential_analytics_api_invalid error: sequential_analytics_key_invalid " ] \
  && echo "PASS double sanitize: only key_invalid and api_invalid" \
  || fail "double sanitize: errors were: $errs"

# Uninstall removes the option (run uninstall.php the way WordPress does, without deleting the read-only files).
wp option get sequential_analytics_settings --format=json
wp plugin deactivate sequential-analytics >/dev/null
un="$(wp eval 'define( "WP_UNINSTALL_PLUGIN", "sequential-analytics/sequential-analytics.php" ); include WP_PLUGIN_DIR . "/sequential-analytics/uninstall.php"; var_dump( get_option( "sequential_analytics_settings", "GONE" ) );' || true)"
echo "$un"
printf '%s\n' "$un" | grep -qF 'string(4) "GONE"' && echo "PASS uninstall removes the option" || fail "uninstall left the option"

# WP_DEBUG is on in the web container: no PHP notice, warning or deprecation may name the plugin.
log="$(docker exec "$P-web" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null' || true)"
hits="$(printf '%s\n' "$log" | grep -c 'sequential-analytics' || true)"
echo "debug.log lines: $(printf '%s' "$log" | grep -c '' || true), naming the plugin: $hits"
[ "$hits" = "0" ] && echo "PASS no PHP notices from the plugin" || { printf '%s\n' "$log" | grep 'sequential-analytics'; fail "PHP notices from the plugin"; }

[ "$status" = 0 ] && echo "SMOKE: ALL PASS" || echo "SMOKE: FAILED"
exit "$status"
