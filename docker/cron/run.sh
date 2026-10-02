#!/bin/sh
# Jedno spuštění plánovaných úloh aplikace. Volá se přes veřejnou doménu, ne interní adresu: odkazy v e-mailech
# z cron úloh se skládají z URL požadavku (docs/Architecture/cron.md). Odpověď jiná než 2xx (403 token, 409 běží
# předchozí běh, 500 úloha selhala) = běh cron jobu na Renderu skončí chybou.
set -eu

: "${CRON_URL:?Nastavte CRON_URL, např. https://<doména>/cron}"
: "${CRON_TOKEN:?Nastavte CRON_TOKEN, stejnou hodnotu jako parameters: cronToken v config.local.neon}"

exec curl -fsS --max-time 300 -H "X-Cron-Token: $CRON_TOKEN" "$CRON_URL"
