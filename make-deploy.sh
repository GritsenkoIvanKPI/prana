#!/bin/bash
# Builds prana-deploy.zip with exactly what belongs in Webuzo's public_html —
# nothing else. Anything placed in public_html is fetchable by direct URL, so
# internal docs/briefs must never go in there.
set -e
cd "$(dirname "$0")"

rm -rf deploy prana-deploy.zip
mkdir -p deploy
cp index.html deploy/
cp send.php deploy/
cp favicon.ico deploy/
cp site.webmanifest deploy/
cp robots.txt deploy/
cp sitemap.xml deploy/
cp -R images deploy/

cd deploy
zip -rq ../prana-deploy.zip .
cd ..
rm -rf deploy

echo "Built prana-deploy.zip — upload its contents to public_html on Webuzo."
echo "Then create telegram-config.php directly on the server (see TELEGRAM_SETUP.md) — never zip real secrets into this archive."
