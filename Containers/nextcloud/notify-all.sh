#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

if [[ "$EUID" = 0 ]]; then
    COMMAND=(su-exec www-data php /var/www/html/occ)
else
    COMMAND=(php /var/www/html/occ)
fi

SUBJECT="$1"
MESSAGE="$2"

# The object-id is limited to 64 characters, so hash the subject to get a
# stable identifier of fixed length that is unique per subject.
OBJECT_ID="$(printf '%s' "$SUBJECT" | md5sum | cut -d ' ' -f1)"

if [ "$("${COMMAND[@]}" config:app:get notifications enabled)" = "no" ]; then
    echo "Cannot send notification as notification app is not enabled."
    exit 1
fi

echo "Posting notifications to all users..."
NC_USERS=$("${COMMAND[@]}" user:list | sed 's|^  - ||g' | sed 's|:.*||')
mapfile -t NC_USERS <<< "$NC_USERS"
for user in "${NC_USERS[@]}"
do
    echo "Posting '$SUBJECT' to: $user"
    "${COMMAND[@]}" notification:generate "$user" "$NC_DOMAIN: $SUBJECT" -l "$MESSAGE" --object-type='update' --object-id="$OBJECT_ID"
done

echo "Done!"
exit 0