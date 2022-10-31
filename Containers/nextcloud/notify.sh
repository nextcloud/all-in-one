#!/bin/bash

if [[ "$EUID" = 0 ]]; then
    COMMAND=(sudo -E -u www-data php /var/www/html/occ)
else
    COMMAND=(php /var/www/html/occ)
fi

SUBJECT="$1"
MESSAGE="$2"

if [ "$("${COMMAND[@]}" config:app:get notifications enabled)" = "no" ]; then
    echo "Cannot send notification as notification app is not enabled."
    exit 1
fi

echo "Posting notifications to users that are admins..."
NC_USERS=$("${COMMAND[@]}" user:list | sed 's|^  - ||g' | sed 's|:.*||')
mapfile -t NC_USERS <<< "$NC_USERS"
for user in "${NC_USERS[@]}"
do
    if "${COMMAND[@]}" user:info "$user" | cut -d "-" -f2 | grep -x -q " admin"
    then
        NC_ADMIN_USER+=("$user")
    fi
done

for admin in "${NC_ADMIN_USER[@]}"
do
    echo "Posting '$SUBJECT' to: $admin"
    "${COMMAND[@]}" notification:generate "$admin" "$NC_DOMAIN: $SUBJECT" -l "$MESSAGE"
done

echo "Done!"
exit 0
