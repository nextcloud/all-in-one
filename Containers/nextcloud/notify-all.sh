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

echo "Posting notifications to all users..."
NC_USERS=$("${COMMAND[@]}" user:list | sed 's|^  - ||g' | sed 's|:.*||')
mapfile -t NC_USERS <<< "$NC_USERS"
for user in "${NC_USERS[@]}"
do
    echo "Posting '$SUBJECT' to: $user"
    "${COMMAND[@]}" notification:generate "$user" "$NC_DOMAIN: $SUBJECT" -l "$MESSAGE"
done

echo "Done!"
exit 0