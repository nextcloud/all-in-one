#!/bin/bash

# Wait until the apache container is ready
while ! nc -z "$APACHE_HOST" "$APACHE_PORT"; do
    echo "Waiting for $APACHE_HOST to become available..."
    sleep 15
done

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

signal_handler() {
    exit 0
}

trap signal_handler SIGINT SIGTERM

sleep inf &
wait $!
