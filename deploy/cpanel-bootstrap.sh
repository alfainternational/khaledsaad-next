#!/usr/bin/env bash
# One-shot bootstrap/update for next.khaledsaad.net on the cPanel host.
# Run from the cPanel Terminal (as the account user):
#   bash <(curl -fsSL https://raw.githubusercontent.com/alfainternational/khaledsaad-next/codex/independent-platform/deploy/cpanel-bootstrap.sh)
# Idempotent: clones on first run, pulls afterwards; never prints secrets.
set -euo pipefail

REPO_URL="https://github.com/alfainternational/khaledsaad-next.git"
BRANCH="codex/independent-platform"
APP_DIR="$HOME/repositories/khaledsaad-next"
ENV_SOURCE="$HOME/khaledsaad-next.env"
PHP="/opt/alt/php83/usr/bin/php"
COMPOSER="/opt/cpanel/composer/bin/composer"

# Self-update: always run the latest committed version of this script.
SELF_URL="https://raw.githubusercontent.com/alfainternational/khaledsaad-next/$BRANCH/deploy/cpanel-bootstrap.sh"
if [ -z "${BOOTSTRAP_NO_SELF_UPDATE:-}" ] && [ -f "$0" ]; then
    _tmp="$(mktemp)"
    if curl -fsSL "$SELF_URL" -o "$_tmp" && [ -s "$_tmp" ] && ! cmp -s "$_tmp" "$0"; then
        cp "$_tmp" "$0" && rm -f "$_tmp"
        echo "==> bootstrap script updated; re-running"
        BOOTSTRAP_NO_SELF_UPDATE=1 exec bash "$0" "$@"
    fi
    rm -f "$_tmp"
fi

[ -x "$PHP" ] || PHP="$(command -v php)"
if [ ! -f "$COMPOSER" ]; then
    COMPOSER="$(command -v composer || true)"
fi

echo "==> PHP: $("$PHP" -v | head -1)"

mkdir -p "$HOME/repositories"
if [ -d "$APP_DIR/.git" ]; then
    echo "==> Updating existing checkout"
    git -C "$APP_DIR" fetch --depth=1 origin "$BRANCH"
    git -C "$APP_DIR" reset --hard "origin/$BRANCH"
else
    if [ -e "$APP_DIR" ]; then
        echo "==> Moving aside non-git directory at $APP_DIR"
        mv "$APP_DIR" "$APP_DIR.pre-bootstrap.$(date +%s)"
    fi
    echo "==> Cloning $REPO_URL ($BRANCH)"
    git clone --depth=1 --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi
cd "$APP_DIR"

if [ ! -f .env ]; then
    if [ -f "$ENV_SOURCE" ]; then
        echo "==> Installing .env from $ENV_SOURCE"
        cp "$ENV_SOURCE" .env
        chmod 600 .env
        # Always mint the application key on the server itself (a key pasted from elsewhere failed once).
        "$PHP" artisan key:generate --force --no-interaction
    else
        echo "!! Missing $ENV_SOURCE and no .env in place. Upload khaledsaad-next.env to \$HOME first." >&2
        exit 1
    fi
fi
# Legacy database for the pre-cutover content import (same credentials as DB_* unless overridden).
grep -q '^LEGACY_DB_DATABASE=' .env || printf '\nLEGACY_DB_DATABASE=kcepljda_my\n' >> .env

echo "==> Installing Composer dependencies"
if [ -n "$COMPOSER" ] && [ -f "$COMPOSER" ]; then
    "$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress
elif [ -n "$COMPOSER" ]; then
    "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress
else
    echo "==> Composer not found; downloading a local copy"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    "$PHP" /tmp/composer-setup.php --install-dir="$HOME/bin" --filename=composer --quiet
    rm -f /tmp/composer-setup.php
    "$PHP" "$HOME/bin/composer" install --no-dev --optimize-autoloader --no-interaction --no-progress
fi

echo "==> Migrating"
"$PHP" artisan migrate --force --no-interaction
if [ ! -f storage/app/.seeded ]; then
    echo "==> Seeding (first run only)"
    "$PHP" artisan db:seed --force --no-interaction && touch storage/app/.seeded
fi
# Stage-1 learning content (programs, 12 paths, the 6-lesson foundations course, diagnosis questions) is imported once;
# later edits live in the admin studio, so the seeders never run again automatically.
if [ ! -f storage/app/.seeded-stage1 ]; then
    echo "==> Importing stage-1 content (once)"
    "$PHP" artisan db:seed --class=AcademySeeder --force --no-interaction         && "$PHP" artisan db:seed --class=AssessmentSeeder --force --no-interaction         && touch storage/app/.seeded-stage1
fi
# Stage-2 lab content (15 tools with templates, chain and three illustrative examples each) is imported once;
# the studio owns later template changes as versions, so LabSeeder never runs again automatically.
if [ ! -f storage/app/.seeded-stage2 ]; then
    echo "==> Importing stage-2 lab content (once)"
    "$PHP" artisan db:seed --class=LabSeeder --force --no-interaction && touch storage/app/.seeded-stage2
fi
"$PHP" artisan storage:link >/dev/null 2>&1 || true
echo "==> Caching"
"$PHP" artisan optimize:clear >/dev/null
"$PHP" artisan optimize
chmod -R u+rwX storage bootstrap/cache
# LiteSpeed must be able to traverse into the docroot.
chmod 711 "$HOME" "$HOME/repositories" "$APP_DIR"
chmod 755 "$APP_DIR/public"

echo "==> Readiness"
"$PHP" artisan platform:doctor --env=production || true
echo "==> Done. Visit https://next.khaledsaad.net/"
