#!/bin/bash

# ENV Variables
MAX_RETRY=3
COUNT=1

# Wait until the domain is reachable
sleep 15
while [ $COUNT -le $MAX_RETRY ]; do
    if nc -z $NC_DOMAIN 443; then
        echo "Domain reached."
        break
    else
        echo "Attempt $COUNT: Domain not reachable. Retrying in 15 seconds..."
        sleep 15
        ((COUNT++))
    fi
done
if [ $COUNT -gt $MAX_RETRY ]; then
    echo "The domain could not be reached after $MAX_RETRY attempts. Proceeding anyway..."
fi

if [ -n "$NEXTCLOUD_EXEC_COMMANDS" ]; then
    echo "#!/bin/bash" > /tmp/nextcloud-exec-commands
    echo "$NEXTCLOUD_EXEC_COMMANDS" >> /tmp/nextcloud-exec-commands
    if ! grep "one-click-instance" /tmp/nextcloud-exec-commands; then
        bash /tmp/nextcloud-exec-commands
        rm /tmp/nextcloud-exec-commands
    fi
else
    # Collabora must work also if using manual-install 
    if [ "$COLLABORA_ENABLED" = yes ]; then
        echo "Activating Collabora config..."
        php /var/www/html/occ richdocuments:activate-config
    fi
    # OnlyOffice must work also if using manual-install
    if [ "$ONLYOFFICE_ENABLED" = yes ]; then
        echo "Activating OnlyOffice config..."
        php /var/www/html/occ onlyoffice:documentserver --check
    fi
fi

sleep inf
