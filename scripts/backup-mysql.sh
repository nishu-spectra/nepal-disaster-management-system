#!/usr/bin/env bash
# Example daily backup script. Configure environment variables and secure destination.
set -euo pipefail
: "${NDMS_DB_HOST:=127.0.0.1}"
: "${NDMS_DB_NAME:=ndms}"
: "${NDMS_DB_USER:?Set NDMS_DB_USER}"
: "${NDMS_DB_PASS:?Set NDMS_DB_PASS}"
: "${NDMS_BACKUP_DIR:?Set NDMS_BACKUP_DIR to a protected location}"
mkdir -p "$NDMS_BACKUP_DIR"
chmod 700 "$NDMS_BACKUP_DIR"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
umask 077
MYSQL_PWD="$NDMS_DB_PASS" mysqldump --host="$NDMS_DB_HOST" --user="$NDMS_DB_USER" --single-transaction --routines --triggers "$NDMS_DB_NAME" | gzip > "$NDMS_BACKUP_DIR/ndms-$stamp.sql.gz"
find "$NDMS_BACKUP_DIR" -type f -name 'ndms-*.sql.gz' -mtime +14 -delete
