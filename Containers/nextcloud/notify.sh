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

echo "Posting notifications to users that are admins..."
# Listing the admin group members with one occ call is much faster than running
# 'occ user:info' per user and is not limited to the first 500 users.
NC_ADMIN_USERS=$("${COMMAND[@]}" group:list admin | sed -n 's|^    - ||p')
if [ -z "$NC_ADMIN_USERS" ]; then
    echo "Could not find any admin user to post notifications to."
    exit 1
fi
mapfile -t NC_ADMIN_USERS <<< "$NC_ADMIN_USERS"

for admin in "${NC_ADMIN_USERS[@]}"
do
    echo "Posting '$SUBJECT' to: $admin"
    "${COMMAND[@]}" notification:generate "$admin" "$NC_DOMAIN: $SUBJECT" -l "$MESSAGE" --object-type='update' --object-id="$OBJECT_ID"
done

echo "Done!"
exit 0
