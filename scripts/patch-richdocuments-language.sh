#!/usr/bin/env bash
# Re-apply language detection fix to richdocuments DocumentGenerationService.php
# Run after richdocuments app updates (comes with AIO container updates).
# Context: Mistral-Small defaults to French/German when given ambiguous "same language" instruction.

set -euo pipefail

CONTAINER="nextcloud-aio-nextcloud"
TARGET="/var/www/html/custom_apps/richdocuments/lib/Service/DocumentGenerationService.php"

docker exec "$CONTAINER" grep -q "do not default to any other language" "$TARGET" && {
  echo "Patch already applied — skipping."
  exit 0
}

docker exec "$CONTAINER" sed -i \
  's/Write the document in the same language as the description\./Detect the language of the description and write the document in that exact language. If the description is in English, write in English. If in French, write in French. Match the description language exactly — do not default to any other language./' \
  "$TARGET"

docker exec "$CONTAINER" sed -i \
  's/Write the CSV content in the same language as the description\./Detect the language of the description and write all CSV content in that exact language. Match the description language exactly — do not default to any other language./' \
  "$TARGET"

echo "Patch applied to $TARGET"
