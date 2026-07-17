#!/usr/bin/env bash
# Re-apply the eurooffice fileId=0 preview fix after AUTOMATIC_UPDATES
# replaces custom_apps/eurooffice on container recreation.
#
# Bug: EditorApiController::getFile() rejects fileId=0 via empty($fileId)
# before trying the $filePath fallback, breaking Files-app inline preview
# of Office documents. Not reported/fixed upstream as of 2026-07-17.
#
# Idempotent: safe to run whether or not the patch is already applied,
# or whether eurooffice is even installed.
#
# Usage:
#   ./patch-eurooffice-fileid.sh
#
# To run manually:  sudo bash /home/coreconduit/projects/nextcloud-aio/scripts/patch-eurooffice-fileid.sh

set -uo pipefail

CONTAINER="nextcloud-aio-nextcloud"
PATCH_PHP="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/patch-eurooffice-fileid.php"

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
  echo "$(date -u +%FT%TZ) SKIP: $CONTAINER is not running"
  exit 0
fi

docker cp "$PATCH_PHP" "$CONTAINER:/tmp/patch-eurooffice-fileid.php"
OUTPUT=$(docker exec --user www-data "$CONTAINER" php /tmp/patch-eurooffice-fileid.php 2>&1)
STATUS=$?
docker exec "$CONTAINER" rm -f /tmp/patch-eurooffice-fileid.php

echo "$(date -u +%FT%TZ) $OUTPUT"
exit $STATUS
