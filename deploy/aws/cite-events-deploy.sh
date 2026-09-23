#!/usr/bin/env bash
set -Eeuo pipefail

# Installed as /usr/local/sbin/cite-events-deploy and run by systemd as root.
# Repository code is never executed as root; only PHP syntax is checked before
# Apache serves the new release.
export PATH=/usr/sbin:/usr/bin:/sbin:/bin
export GIT_TERMINAL_PROMPT=0
export GIT_SSH_COMMAND='ssh -i /root/.ssh/cite-events-deploy -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes'
check_only=false
if [[ "${1:-}" == '--check' ]]; then
    check_only=true
elif [[ "$#" -ne 0 ]]; then
    printf 'Usage: %s [--check]\n' "$0" >&2
    exit 2
fi

repository='git@github.com:HadaC05/IT-Event-Management.git'
state='/srv/cite-events'
staging="${state}/staging"
releases="${state}/releases"
shared="${state}/shared"
legacy="${state}/legacy"
webroot='/var/www/html/ITEventManagement'
database_config='/etc/cite-events/mysql-backup.cnf'
database_name='event_db'
hostname='cite-events.duckdns.org'
stage=''
target=''

log() { printf '[%s] %s\n' "$(date -Is)" "$*"; }
fail() { log "FAILED: $*" >&2; exit 1; }

finish() {
    status="$1"
    failed_commit="${actual:-$target}"
    if [[ "$status" -ne 0 && "$failed_commit" =~ ^[0-9a-f]{40}$ ]]; then
        printf '%s\n' "$failed_commit" > "${state}/last_failure"
    fi
    if [[ -n "$stage" && -d "$stage" && "$stage" == "$staging"/deploy.* ]]; then
        rm -rf -- "$stage"
    fi
}
trap 'finish $?' EXIT

install -d -m 0755 "$state" "$staging" "$releases" "$shared" "$legacy"
exec 9>/run/lock/cite-events-deploy.lock
flock -n 9 || exit 0

target="$(git ls-remote --exit-code "$repository" refs/heads/main | awk '{print $1}')"
[[ "$target" =~ ^[0-9a-f]{40}$ ]] || fail 'GitHub did not return a valid main commit.'
current="$(cat "${state}/current_commit" 2>/dev/null || true)"
if [[ "$target" == "$current" && "$check_only" != true ]]; then
    exit 0
fi
if [[ "$check_only" != true && "$(cat "${state}/last_failure" 2>/dev/null || true)" == "$target" ]]; then
    last_failure_time="$(stat -c %Y "${state}/last_failure")"
    if (( $(date +%s) - last_failure_time < 300 )); then
        exit 0
    fi
fi

log "Preparing main commit ${target}."
stage="$(mktemp -d "${staging}/deploy.XXXXXXXX")"
git clone --quiet --depth 1 --single-branch --branch main "$repository" "${stage}/repo"
actual="$(git -C "${stage}/repo" rev-parse HEAD)"
[[ "$actual" =~ ^[0-9a-f]{40}$ ]] || fail 'The checkout has no valid commit.'
if [[ "$actual" == "$current" && "$check_only" != true ]]; then
    exit 0
fi

install -d -m 0755 "${stage}/release"
git -C "${stage}/repo" archive HEAD | tar -xf - -C "${stage}/release"
test -f "${stage}/release/pages/home.html" || fail 'The homepage is missing.'
test -f "${stage}/release/js/vendor/axios.min.js" || fail 'Axios is missing.'
test -f "${stage}/release/.htaccess" || fail 'The application rewrite rules are missing.'
test -f "${stage}/release/assets/uploads/.htaccess" || fail 'Upload execution protection is missing.'
test -f "${shared}/db_connect.php" || fail 'The production database connector is missing.'
test -f "$database_config" || fail 'The production database credentials are missing.'

while IFS= read -r -d '' php_file; do
    php -l "$php_file" >/dev/null || fail "PHP syntax failed: ${php_file#"${stage}/release/"}"
done < <(find "${stage}/release/api" -type f -name '*.php' -print0)

shopt -s nullglob
migration_files=("${stage}/release/database/deploy_migrations/"*.sql)
for migration in "${migration_files[@]}"; do
    name="$(basename "$migration")"
    [[ "$name" =~ ^[0-9]{8}_[0-9]{4}_[a-z0-9_]+\.sql$ ]] || fail "Invalid migration name: $name"
    if grep -Eiq '\b(DROP[[:space:]]+(DATABASE|TABLE|COLUMN)|TRUNCATE|DELETE[[:space:]]+FROM|RENAME[[:space:]]+TABLE)\b' "$migration"; then
        fail "Migration $name contains a destructive statement; release needs review."
    fi
done

if [[ "$check_only" == true ]]; then
    log "Preflight passed for ${actual}; no live state changed."
    exit 0
fi

if [[ -d "${releases}/${actual}" ]]; then
    candidate="${releases}/${actual}"
else
    candidate="${stage}/release"
fi

log 'Taking a fresh database backup.'
/usr/local/sbin/cite-events-backup

mysql --defaults-extra-file="$database_config" -D "$database_name" -e '
    CREATE TABLE IF NOT EXISTS tbl_deployment_migrations (
        migration_name VARCHAR(255) PRIMARY KEY,
        sha256 CHAR(64) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'

# A migration file is immutable once applied. A replacement needs a new name.
for migration in "${migration_files[@]}"; do
    name="$(basename "$migration")"
    hash="$(sha256sum "$migration" | awk '{print $1}')"
    recorded="$(mysql --defaults-extra-file="$database_config" -D "$database_name" -N -B -e "SELECT sha256 FROM tbl_deployment_migrations WHERE migration_name='${name}'")"
    if [[ -n "$recorded" && "$recorded" != "$hash" ]]; then
        fail "Previously applied migration $name was edited."
    fi
done

for migration in "${migration_files[@]}"; do
    name="$(basename "$migration")"
    hash="$(sha256sum "$migration" | awk '{print $1}')"
    recorded="$(mysql --defaults-extra-file="$database_config" -D "$database_name" -N -B -e "SELECT sha256 FROM tbl_deployment_migrations WHERE migration_name='${name}'")"
    if [[ -z "$recorded" ]]; then
        log "Applying migration $name."
        mysql --defaults-extra-file="$database_config" -D "$database_name" < "$migration"
        mysql --defaults-extra-file="$database_config" -D "$database_name" -e "INSERT INTO tbl_deployment_migrations (migration_name, sha256) VALUES ('${name}', '${hash}')"
    fi
done

user_count="$(mysql --defaults-extra-file="$database_config" -D "$database_name" -N -B -e 'SELECT COUNT(*) FROM tbl_users')"
[[ "$user_count" =~ ^[0-9]+$ && "$user_count" -gt 0 ]] || fail 'The live user table is empty or unavailable.'

if [[ "$candidate" == "${stage}/release" ]]; then
    # Keep generated media outside Git releases. Never replace a live upload
    # with a similarly named file committed to GitHub.
    rsync -a --ignore-existing "${candidate}/assets/uploads/" "${shared}/uploads/"
    chown -R www-data:www-data "${shared}/uploads"
    find "${shared}/uploads" -type d -exec chmod 0775 {} +
    find "${shared}/uploads" -type f -exec chmod 0644 {} +
    chown root:www-data "${shared}/uploads/.htaccess"
    chmod 0644 "${shared}/uploads/.htaccess"

    mv "${candidate}/assets/uploads" "${stage}/packaged-uploads"
    find "$candidate" -type d -exec chmod 0755 {} +
    find "$candidate" -type f -exec chmod 0644 {} +
    chown -R root:www-data "$candidate"
    ln -s "${shared}/uploads" "${candidate}/assets/uploads"
    ln -s "${shared}/db_connect.php" "${candidate}/api/db_connect.php"
    mv "$candidate" "${releases}/${actual}"
    candidate="${releases}/${actual}"
fi

apache2ctl configtest >/dev/null || fail 'Apache configuration is invalid.'
next_link="${webroot}.deploy.$$"
ln -s "$candidate" "$next_link"

if [[ -L "$webroot" ]]; then
    previous="$(readlink -f "$webroot")"
    [[ "$previous" == "$releases"/* ]] || fail 'Current release points outside the release directory.'
elif [[ -d "$webroot" ]]; then
    previous="${legacy}/initial-$(date -u +%Y%m%dT%H%M%SZ)"
    mv "$webroot" "$previous"
else
    fail 'The current site is neither a release symlink nor the initial site directory.'
fi

mv -Tf "$next_link" "$webroot"
healthy=true
systemctl reload apache2 || healthy=false
curl --fail --silent --show-error --max-time 15 --resolve "${hostname}:443:127.0.0.1" \
    "https://${hostname}/ITEventManagement/" >/dev/null || healthy=false
session_response="$(curl --fail --silent --show-error --max-time 15 --resolve "${hostname}:443:127.0.0.1" \
    "https://${hostname}/ITEventManagement/api/auth.php?action=session")" || healthy=false
[[ "$session_response" == *'"success":true'* ]] || healthy=false
private_status="$(curl --silent --show-error --max-time 15 --output /dev/null --write-out '%{http_code}' \
    --resolve "${hostname}:443:127.0.0.1" "https://${hostname}/ITEventManagement/api/users.php")" || healthy=false
[[ "$private_status" == '401' ]] || healthy=false
dump_status="$(curl --silent --show-error --max-time 15 --output /dev/null --write-out '%{http_code}' \
    --resolve "${hostname}:443:127.0.0.1" "https://${hostname}/ITEventManagement/database/event_db.sql")" || healthy=false
[[ "$dump_status" == '403' ]] || healthy=false

if [[ "$healthy" != true ]]; then
    log "Health check failed for ${actual}; restoring the prior code release."
    if [[ "$previous" == "$legacy"/* ]]; then
        test -L "$webroot" && rm -- "$webroot"
        mv "$previous" "$webroot"
    else
        rollback_link="${webroot}.rollback.$$"
        ln -s "$previous" "$rollback_link"
        mv -Tf "$rollback_link" "$webroot"
    fi
    systemctl reload apache2
    fail 'New code was rolled back. Database migrations remain applied; inspect the backup and log.'
fi

printf '%s\n' "$actual" > "${state}/current_commit.next"
mv -f "${state}/current_commit.next" "${state}/current_commit"
if [[ -f "${state}/last_failure" ]]; then
    rm -- "${state}/last_failure"
fi
log "Deployed ${actual}; ${user_count} users retained."
