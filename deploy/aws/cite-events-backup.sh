#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

backup_directory='/var/backups/cite-events'
database_config='/etc/cite-events/mysql-backup.cnf'
install -d -m 0700 "$backup_directory"

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
target="${backup_directory}/event_db_${stamp}.sql.gz"
mysqldump --defaults-extra-file="$database_config" \
    --single-transaction --quick --routines --triggers --events \
    --hex-blob --no-tablespaces --default-character-set=utf8mb4 event_db \
    | gzip -9 > "$target"
gzip -t "$target"
sha256sum "$target" > "${target}.sha256"

# Keep a week of backups, including those made just before every deployment.
while IFS= read -r -d '' old_backup; do
    rm -- "$old_backup"
    if [[ -f "${old_backup}.sha256" ]]; then
        rm -- "${old_backup}.sha256"
    fi
done < <(find "$backup_directory" -maxdepth 1 -type f \
    -name 'event_db_*.sql.gz' -mtime +7 -print0)
