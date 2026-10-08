#!/bin/sh
# Start kontejneru (Dockerfile): dodá theme/config/config.local.neon ze Secret File Renderu a spustí příkaz.
# Bez argumentů spustí Apache, jinak daný příkaz jako www-data (např. pre-deploy migrace v render.yaml).
set -eu

APP_DIR=/var/www/html
SECRET_FILE="${CONFIG_LOCAL_NEON:-/etc/secrets/config.local.neon}"
TARGET="$APP_DIR/theme/config/config.local.neon"

# Bez roota (druhé volání přes runuser níže) už je připraveno
if [ "$(id -u)" != 0 ]; then
	exec "$@"
fi

if [ -f "$SECRET_FILE" ]; then
	install -m 0640 -o root -g www-data "$SECRET_FILE" "$TARGET"
elif [ ! -f "$TARGET" ]; then
	echo "app-entrypoint: chybí $SECRET_FILE (Secret File config.local.neon na Renderu), viz doc/render-deploy.md" >&2
	exit 1
fi

# Release pro Sentry = commit, ze kterého je deploy
if [ -z "${SENTRY_RELEASE:-}" ] && [ -n "${RENDER_GIT_COMMIT:-}" ]; then
	export SENTRY_RELEASE="$RENDER_GIT_COMMIT"
fi

if [ "${1:-}" = apache2-foreground ]; then
	# Záloha za pre-deploy příkaz (free plán ho nemá): migrace při startu instance
	if [ "${MIGRATE_ON_START:-0}" = 1 ]; then
		# srovná kontrolní součty upravených migrací, jinak migrations:continue odmítne pokračovat
		runuser -u www-data -- php "$APP_DIR/bin/console" migrations:repairChecksum
		runuser -u www-data -- php "$APP_DIR/bin/console" migrations:continue
	fi
	# Apache předává do PHP jen proměnné z PassEnv (apache-vhost.conf)
	if [ -n "${SENTRY_RELEASE:-}" ]; then
		set -- "$@" -DSENTRY_RELEASE
	fi
	exec "$@"
fi

# temp/cache musí patřit www-data, jinak do něj web po CLI nezapíše (docs/AI-Context/gotchas.md)
exec runuser -u www-data -- "$@"
