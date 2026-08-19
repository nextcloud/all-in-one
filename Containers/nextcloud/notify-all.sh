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
OBJECT_ID="$(printf '%s' "$SUBJECT" | sha256sum | cut -c1-64)"

if [ "$("${COMMAND[@]}" config:app:get notifications enabled)" = "no" ]; then
    echo "Cannot send notification as notification app is not enabled."
    exit 1
fi

echo "Posting notifications to all users..."
# 'occ user:list' only returns the first 500 users by default, so disable the limit.
NC_USERS=$("${COMMAND[@]}" user:list --limit=0 | sed -n 's|^  - ||p' | sed 's|:.*||')
if [ -z "$NC_USERS" ]; then
    echo "Could not find any user to post notifications to."
    exit 1
fi
mapfile -t NC_USERS <<< "$NC_USERS"
for user in "${NC_USERS[@]}"
do
    echo "Posting '$SUBJECT' to: $user"
    "${COMMAND[@]}" notification:generate "$user" "$NC_DOMAIN: $SUBJECT" -l "$MESSAGE" --object-type='update' --object-id="$OBJECT_ID"
done

echo "Done!"
exit 0