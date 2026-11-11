#!/usr/bin/env bash
# المرحلة 5 — نقل khaledsaad.net من الموقع القديم إلى المنصة (runbook: docs/runbooks/domain-cutover.md).
#
# Run it FROM THE CHECKOUT, never through curl:
#   bash ~/repositories/khaledsaad-next/deploy/cpanel-cutover.sh
#   bash ~/repositories/khaledsaad-next/deploy/cpanel-cutover.sh --rollback
#
# That is deliberate. The script living in the checkout is itself the proof that the deploy ran first: cutting over to
# code that predates the legacy-redirect fix would answer twenty indexed URLs with a permanent redirect into a 404.
#
# What it does, in order, and stops at the first thing that fails:
#   1. refuses unless the checkout is clean, at origin/BRANCH, and running the redirect fix;
#   2. backs up the old docroot and the old database (both, or it does not continue);
#   3. swaps the docroot, then VERIFIES the live domain — and rolls itself back if the domain does not answer;
#   4. points the three environment values at the final domain and rebuilds the caches;
#   5. prints the readiness check and the two things only a human can do (PayPal, Search Console).
#
# Idempotent: a second run reports that the cutover already happened and changes nothing. Never prints a secret.
set -euo pipefail

BRANCH="codex/independent-platform"
APP_DIR="$HOME/repositories/khaledsaad-next"
DOCROOT="$HOME/public_html"
OLD_DOCROOT="$HOME/public_html_old"
DOMAIN="khaledsaad.net"
STAGING_DOMAIN="next.khaledsaad.net"
BACKUP_DIR="$HOME/cutover-backups"
PHP="/opt/alt/php83/usr/bin/php"
[ -x "$PHP" ] || PHP="$(command -v php)"

step() { printf '\n==> %s\n' "$1"; }
fail() { printf '\n!! %s\n' "$1" >&2; exit 1; }

# ---------------------------------------------------------------------------------------------------------------------
# .env editing. Every write keeps the file at 600 (it holds live payment and provider credentials) and never echoes a
# value, so this script's output can be pasted into a chat without redacting it.
# ---------------------------------------------------------------------------------------------------------------------
env_file() { printf '%s\n' "$APP_DIR/.env"; }

env_get() {
    local key="$1"
    sed -n "s/^${key}=//p" "$(env_file)" | tail -1
}

set_env() {
    local key="$1" value="$2" file
    file="$(env_file)"
    if grep -q "^${key}=" "$file"; then
        # The value is written through a sed script built from a file so a password containing / or & cannot break it.
        local tmp
        tmp="$(mktemp)"
        awk -v k="$key" -v v="$value" -F= '
            $1 == k { print k "=" v; next } { print }
        ' "$file" > "$tmp"
        cat "$tmp" > "$file"
        rm -f "$tmp"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
    chmod 600 "$file"
    printf '    %s set\n' "$key"
}

# ---------------------------------------------------------------------------------------------------------------------
# Rollback. One minute, and it reverts BOTH halves — the docroot and the environment.
#
# The runbook's original rollback restored the docroot alone, which would have left the platform still announcing
# itself as khaledsaad.net while that domain served the old site again: every link and every redirect the staging
# host generated would have pointed at the site we had just rolled back to. Half a rollback is a worse state than
# the one it is undoing, because it looks finished.
# ---------------------------------------------------------------------------------------------------------------------
rollback() {
    step "Rolling back"
    if [ -L "$DOCROOT" ] && [ -d "$OLD_DOCROOT" ]; then
        rm -f "$DOCROOT"
        mv "$OLD_DOCROOT" "$DOCROOT"
        echo "    docroot restored to the old site"
    elif [ -d "$OLD_DOCROOT" ]; then
        fail "$DOCROOT is not the symlink this script made. Move it aside by hand, then: mv $OLD_DOCROOT $DOCROOT"
    else
        echo "    docroot already points at the old site"
    fi

    if [ -f "$APP_DIR/.env" ]; then
        set_env APP_URL "https://$STAGING_DOMAIN"
        set_env PLATFORM_PUBLIC_HOST "$STAGING_DOMAIN"
        set_env SESSION_DOMAIN "$STAGING_DOMAIN"
        ( cd "$APP_DIR" && "$PHP" artisan optimize:clear >/dev/null && "$PHP" artisan optimize >/dev/null )
        echo "    platform points back at $STAGING_DOMAIN"
    fi

    printf '\nRolled back. The old site is live on %s and the platform is live on %s.\n' "$DOMAIN" "$STAGING_DOMAIN"
    echo "Nothing touched the platform database, so no data was lost either way."
}

if [ "${1:-}" = "--rollback" ]; then
    rollback
    exit 0
fi

SKIP_DB_BACKUP=""
[ "${1:-}" = "--skip-db-backup" ] && SKIP_DB_BACKUP=1

# ---------------------------------------------------------------------------------------------------------------------
# 1. Preconditions. Each one is a reason the cutover would go wrong in a way that is hard to see afterwards.
# ---------------------------------------------------------------------------------------------------------------------
step "Preconditions"

[ -d "$APP_DIR/.git" ] || fail "No checkout at $APP_DIR. Run the bootstrap script first."
[ -f "$APP_DIR/.env" ] || fail "No .env at $APP_DIR. Run the bootstrap script first."

if [ -L "$DOCROOT" ] && [ "$(readlink "$DOCROOT")" = "repositories/khaledsaad-next/public" ]; then
    echo "    already cut over: $DOCROOT -> $(readlink "$DOCROOT")"
    echo "    nothing to do. Use --rollback to undo it."
    exit 0
fi
[ -e "$OLD_DOCROOT" ] && fail "$OLD_DOCROOT already exists — an earlier attempt left it there. Inspect it before continuing; this script will not overwrite a backup of the live site."
[ -d "$DOCROOT" ] || fail "$DOCROOT is missing or is not a directory. Nothing to move aside."

cd "$APP_DIR"
# Tracked files only. A deployed checkout always carries untracked runtime leftovers — a storage symlink, a cache
# file, a marker — and refusing to move the domain because of one of those is a guard that cries wolf. What matters
# is that the code being served is the code that was reviewed, which is this check plus the HEAD comparison below.
dirty="$(git status --porcelain --untracked-files=no)"
if [ -n "$dirty" ]; then
    printf '%s\n' "$dirty" | sed 's/^/        /'
    fail "The files above differ from the commit. A guard that will not say what it found is not worth obeying, so there they are: restore them (git -C $APP_DIR checkout -- <path>) or re-run the bootstrap, which resets to origin/$BRANCH."
fi
git fetch --depth=1 origin "$BRANCH" --quiet
local_head="$(git rev-parse HEAD)"
remote_head="$(git rev-parse "origin/$BRANCH")"
[ "$local_head" = "$remote_head" ] || fail "Deployed $(git rev-parse --short HEAD) but origin/$BRANCH is $(git rev-parse --short "origin/$BRANCH"). Run the bootstrap script first."
echo "    checkout clean at $(git rev-parse --short HEAD)"

# Asked of the RUNNING application, not of a file: this proves the deployed code resolves a legacy redirect at request
# time. Without it, cutover day answers twenty indexed /blog/{slug} URLs with a permanent redirect into a 404.
readiness="$("$PHP" artisan platform:doctor --env=production || true)"
printf '%s\n' "$readiness" | grep -q 'legacy redirects reach their article' \
    || fail "The deployed code predates the legacy-redirect fix (platform:doctor does not report it). Run the bootstrap script, then this one."
echo "    legacy redirects are resolved at request time"

if printf '%s\n' "$readiness" | grep -q '^\[FAIL\]'; then
    printf '%s\n' "$readiness" | grep '^\[FAIL\]'
    fail "platform:doctor reports a hard failure. Fix it before moving the domain."
fi

# ---------------------------------------------------------------------------------------------------------------------
# 2. Backups. "I took a backup" must never be a thing this script says without having done it.
# ---------------------------------------------------------------------------------------------------------------------
step "Backing up the old site"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
stamp="$(date +%Y%m%d-%H%M%S)"
files_backup="$BACKUP_DIR/old-site-files-$stamp.tgz"

tar czf "$files_backup" -C "$HOME" public_html
[ -s "$files_backup" ] || fail "The docroot archive came out empty."
echo "    files: $files_backup ($(du -h "$files_backup" | cut -f1))"

if [ -n "$SKIP_DB_BACKUP" ]; then
    echo "    database: SKIPPED at your request (--skip-db-backup)"
else
    command -v mysqldump >/dev/null || fail "mysqldump not found on this host. Take a JetBackup database snapshot, then re-run with --skip-db-backup."
    legacy_db="$(env_get LEGACY_DB_DATABASE)"
    [ -n "$legacy_db" ] && legacy_db="${legacy_db//\"/}"
    [ -n "$legacy_db" ] || fail "LEGACY_DB_DATABASE is not set in .env, so the old database cannot be named."
    db_backup="$BACKUP_DIR/old-site-db-$stamp.sql.gz"

    # The credentials go through a 600 defaults file, never on the command line: an argument is visible in `ps` to
    # every other account on a shared host.
    db_host="$(env_get DB_HOST)"
    cnf="$(mktemp)"
    chmod 600 "$cnf"
    {
        printf '[client]\nuser=%s\npassword="%s"\nhost=%s\n' \
            "$(env_get DB_USERNAME)" "$(env_get DB_PASSWORD)" "${db_host:-127.0.0.1}"
    } > "$cnf"
    trap 'rm -f "$cnf"' EXIT
    mysqldump --defaults-extra-file="$cnf" --single-transaction --quick --no-tablespaces "$legacy_db" | gzip > "$db_backup" \
        || fail "The old database did not dump. Nothing has been moved; take a JetBackup snapshot or re-run with --skip-db-backup."
    rm -f "$cnf"
    trap - EXIT
    [ -s "$db_backup" ] || fail "The database dump came out empty."
    echo "    database: $db_backup ($(du -h "$db_backup" | cut -f1))"
fi

cp -p "$APP_DIR/.env" "$BACKUP_DIR/platform-env-$stamp"
chmod 600 "$BACKUP_DIR/platform-env-$stamp"
echo "    platform .env: $BACKUP_DIR/platform-env-$stamp"

# ---------------------------------------------------------------------------------------------------------------------
# 3. The swap, then proof. The domain is down for the seconds between these two lines, so nothing slow happens here.
# ---------------------------------------------------------------------------------------------------------------------
step "Swapping the docroot"
mv "$DOCROOT" "$OLD_DOCROOT"
ln -s "repositories/khaledsaad-next/public" "$DOCROOT"
chmod 711 "$HOME" "$HOME/repositories" "$APP_DIR"
chmod 755 "$APP_DIR/public"
echo "    $DOCROOT -> $(readlink "$DOCROOT")"

step "Checking that the domain answers"
probe() {
    local out
    # Two attempts, in this order on purpose: the public name first, because that is what a reader uses, and only
    # then the same name pinned to this host — which tells us the platform is up even if DNS is the thing that broke.
    out="$(curl -ksS -o /dev/null -w '%{http_code}' --max-time 20 "https://$DOMAIN/" 2>/dev/null || true)"
    if [ -z "$out" ] || [ "$out" = "000" ]; then
        out="$(curl -ksS -o /dev/null -w '%{http_code}' --max-time 20 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/" 2>/dev/null || true)"
    fi
    printf '%s' "${out:-000}"
}
code="$(probe)"
if [ "$code" != "200" ]; then
    sleep 3
    code="$(probe)"
fi

if [ "$code" != "200" ]; then
    printf '!! https://%s answered %s after the swap.\n' "$DOMAIN" "$code" >&2
    echo "!! Rolling back now rather than leaving the domain broken." >&2
    rollback
    fail "Cutover reverted. The usual cause is that LiteSpeed refuses a symlinked docroot: use the copy path in the runbook (المرحلة 2، خطوة 3) instead."
fi
echo "    https://$DOMAIN/ answered 200"

# ---------------------------------------------------------------------------------------------------------------------
# 4. The platform now has a new name. Until these three lines change it still signs links, cookies and canonicals
#    with the staging host, which is the kind of thing that works in a browser and quietly breaks sitemaps and mail.
# ---------------------------------------------------------------------------------------------------------------------
step "Pointing the platform at $DOMAIN"
set_env APP_URL "https://$DOMAIN"
set_env PLATFORM_PUBLIC_HOST "$DOMAIN"
set_env SESSION_DOMAIN "$DOMAIN"
if [ -n "${PAYPAL_WEBHOOK_ID:-}" ]; then
    set_env PAYPAL_WEBHOOK_ID "$PAYPAL_WEBHOOK_ID"
fi

step "Rebuilding caches"
"$PHP" artisan optimize:clear >/dev/null
"$PHP" artisan optimize
"$PHP" artisan schedule:run >/dev/null 2>&1 || true

# ---------------------------------------------------------------------------------------------------------------------
# 5. Report. The checks that can be made from here are made from here; the rest is named, not implied.
# ---------------------------------------------------------------------------------------------------------------------
step "Verifying"
for path in "/" "/sitemap.xml" "/articles" "/tools" "/login"; do
    printf '    %-14s %s\n' "$path" "$(curl -ksS -o /dev/null -w '%{http_code}' --max-time 20 "https://$DOMAIN$path" || echo 000)"
done
printf '    %-14s %s (expects 301 or 302)\n' "/blog" "$(curl -ksS -o /dev/null -w '%{http_code}' --max-time 20 "https://$DOMAIN/blog" || echo 000)"

step "Readiness"
"$PHP" artisan platform:doctor --env=production || true

cat <<REPORT

==> Cutover done. https://$DOMAIN/ is the platform.

Backups (keep them until the 72 hours in المرحلة 3 are over):
  $files_backup
  ${db_backup:-(database skipped)}
  $BACKUP_DIR/platform-env-$stamp

Rollback, one minute, both halves:
  bash $APP_DIR/deploy/cpanel-cutover.sh --rollback

Only a human can do these two, and both are outward-facing:
  1. PayPal → point the webhook at https://$DOMAIN/webhooks/paypal. If that gives you a NEW webhook id:
       cd $APP_DIR && sed -i 's#^PAYPAL_WEBHOOK_ID=.*#PAYPAL_WEBHOOK_ID=<new id>#' .env && $PHP artisan optimize
     A stale id does not fail loudly: payments still complete and the platform simply never hears about them.
  2. Search Console + Bing → submit https://$DOMAIN/sitemap.xml and watch Pages for 404s.

Not yet, on purpose: $STAGING_DOMAIN still serves the platform. Leave it two stable days, then 301 it to
$DOMAIN from cPanel (Domains → Redirects). Redirecting it today would remove the only way back in that does
not touch the main domain.
REPORT
