#!/bin/bash

# Function to show text in green
print_green() {
    local TEXT="$1"
    printf "%b%s%b\n" "\e[0;92m" "$TEXT" "\e[0m"
}

# Function to check if number was provided
check_if_number() {
case "${1}" in
    ''|*[!0-9]*) return 1 ;;
    *) return 0 ;;
esac
}

# Check if socket is available and readable
if ! [ -a "/var/run/docker.sock" ]; then
    echo "Docker socket is not available. Cannot continue."
    exit 1
elif ! mountpoint -q "/mnt/docker-aio-config"; then
    echo "/mnt/docker-aio-config is not a mountpoint. Cannot proceed!"
    exit 1
elif ! sudo -u www-data test -r /var/run/docker.sock; then
    echo "Trying to fix docker.sock permissions internally..."
    DOCKER_GROUP=$(stat -c '%G' /var/run/docker.sock)
    DOCKER_GROUP_ID=$(stat -c '%g' /var/run/docker.sock)
    # Check if a group with the same group id of /var/run/docker.socket already exists in the container
    if grep -q "^$DOCKER_GROUP:" /etc/group; then
        # If yes, add www-data to that group
        echo "Adding internal www-data to group $DOCKER_GROUP"
        usermod -aG "$DOCKER_GROUP" www-data
    else
        # Delete the docker group for cases when the docker socket permissions changed between restarts
        groupdel docker &>/dev/null

        # If the group doesn't exist, create it
        echo "Creating docker group internally with id $DOCKER_GROUP_ID"
        groupadd -g "$DOCKER_GROUP_ID" docker
        usermod -aG docker www-data
    fi
    if ! sudo -u www-data test -r /var/run/docker.sock; then
        echo "Docker socket is not readable by the www-data user. Cannot continue."
        exit 1
    fi
fi

# Check if api version is supported
if ! sudo -u www-data docker info &>/dev/null; then
    echo "Cannot connect to the docker socket. Cannot proceed."
    exit 1
fi
API_VERSION_FILE="$(find ./ -name DockerActionManager.php | head -1)"
API_VERSION="$(grep -oP 'const API_VERSION.*\;' "$API_VERSION_FILE" | grep -oP '[0-9]+.[0-9]+' | head -1)"
# shellcheck disable=SC2001
API_VERSION_NUMB="$(echo "$API_VERSION" | sed 's/\.//')"
LOCAL_API_VERSION_NUMB="$(sudo -u www-data docker version | grep -i "api version" | grep -oP '[0-9]+.[0-9]+' | head -1 | sed 's/\.//')"
if [ -n "$LOCAL_API_VERSION_NUMB" ] && [ -n "$API_VERSION_NUMB" ]; then
    if ! [ "$LOCAL_API_VERSION_NUMB" -ge "$API_VERSION_NUMB" ]; then
        echo "Docker API v$API_VERSION is not supported by your docker engine. Cannot proceed. Please upgrade your docker engine if you want to run Nextcloud AIO!"
        exit 1
    fi
else
    echo "LOCAL_API_VERSION_NUMB or API_VERSION_NUMB are not set correctly. Cannot check if the API version is supported."
    sleep 10
fi

# Check if startup command was executed correctly
if ! sudo -u www-data docker ps | grep -q "nextcloud-aio-mastercontainer"; then
    echo "It seems like you did not give the mastercontainer the correct name?
Using a different name is not supported!"
    exit 1
elif ! sudo -u www-data docker volume ls | grep -q "nextcloud_aio_mastercontainer"; then
    echo "It seems like you did not give the mastercontainer volume the correct name?
Using a different name is not supported!"
    exit 1
fi

# Check for other options
if [ -n "$NEXTCLOUD_DATADIR" ]; then
    if ! echo "$NEXTCLOUD_DATADIR" | grep -q "^/" || [ "$NEXTCLOUD_DATADIR" = "/" ]; then
        echo "You've set NEXTCLOUD_DATADIR but not to an allowed value.
The string must start with '/' and must not be equal to '/'.
It is set to '$NEXTCLOUD_DATADIR'."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_MOUNT" ]; then
    if ! echo "$NEXTCLOUD_MOUNT" | grep -q "^/" || [ "$NEXTCLOUD_MOUNT" = "/" ]; then
        echo "You've set NEXCLOUD_MOUNT but not to an allowed value.
The string must start with '/' and must not be equal to '/'.
It is set to '$NEXTCLOUD_MOUNT'."
        exit 1
    elif [ "$NEXTCLOUD_MOUNT" = "/mnt/ncdata" ] || echo "$NEXTCLOUD_MOUNT" | grep -q "^/mnt/ncdata/"; then
        echo "'/mnt/ncdata' and '/mnt/ncdata/' are not allowed as values for NEXTCLOUD_MOUNT."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_DATADIR" ] && [ -n "$NEXTCLOUD_MOUNT" ]; then
    if [ "$NEXTCLOUD_DATADIR" = "$NEXTCLOUD_MOUNT" ]; then
        echo "NEXTCLOUD_DATADIR and NEXTCLOUD_MOUNT are not allowed to be equal."
        exit 1
    fi
fi
if [ -n "$APACHE_PORT" ]; then
    if ! check_if_number "$APACHE_PORT"; then
        echo "You provided an Apache port but did not only use numbers.
It is set to '$APACHE_PORT'."
        exit 1
    elif ! [ "$APACHE_PORT" -le 65535 ] || ! [ "$APACHE_PORT" -ge 1 ]; then
        echo "The provided Apache port is invalid. It must be between 1 and 65535"
        exit 1
    fi
fi

# Check DNS resolution
# Prevents issues like https://github.com/nextcloud/all-in-one/discussions/565
curl https://nextcloud.com &>/dev/null
if [ "$?" = 6 ]; then
    echo "Could not resolve the host nextcloud.com."
    echo "Most likely the DNS resolving does not work."
    echo "You should be able to fix this by adding the '--dns=\"ip.address.of.dns.server\"' option to the docker run command."
    exit 1
fi

# Add important folders
mkdir -p /mnt/docker-aio-config/data/
mkdir -p /mnt/docker-aio-config/session/
mkdir -p /mnt/docker-aio-config/caddy/
mkdir -p /mnt/docker-aio-config/certs/ 

# Adjust permissions for all instances
chmod 770 -R /mnt/docker-aio-config
chmod 777 /mnt/docker-aio-config
chown www-data:www-data -R /mnt/docker-aio-config/data/
chown www-data:www-data -R /mnt/docker-aio-config/session/
chown www-data:www-data -R /mnt/docker-aio-config/caddy/
chown root:root -R /mnt/docker-aio-config/certs/

# Don't allow access to the AIO interface from the Nextcloud container
# Probably more cosmetic than anything but at least an attempt
if ! grep -q '# nextcloud-aio-block' /etc/apache2/apache2.conf; then
    cat << APACHE_CONF >> /etc/apache2/apache2.conf
# nextcloud-aio-block-start
<Location />
order allow,deny
deny from nextcloud-aio-nextcloud.nextcloud-aio
allow from all
</Location>
# nextcloud-aio-block-end
APACHE_CONF
fi

# Adjust certs
GENERATED_CERTS="/mnt/docker-aio-config/certs"
TMP_CERTS="/etc/apache2/certs"
mkdir -p "$GENERATED_CERTS"
cd "$GENERATED_CERTS" || exit 1
if ! [ -f ./ssl.crt ] && ! [ -f ./ssl.key ]; then
    openssl req -new -newkey rsa:4096 -days 3650 -nodes -x509 -subj "/C=DE/ST=BE/L=Local/O=Dev/CN=nextcloud.local" -keyout ./ssl.key -out ./ssl.crt
fi
if [ -f ./ssl.crt ] && [ -f ./ssl.key ]; then
    cd "$TMP_CERTS" || exit 1
    rm ./ssl.crt
    rm ./ssl.key
    cp "$GENERATED_CERTS/ssl.crt" ./
    cp "$GENERATED_CERTS/ssl.key" ./
fi

print_green "Initial startup of Nextcloud All In One complete!
You should be able to open the Nextcloud AIO Interface now on port 8080 of this server!
E.g. https://internal.ip.of.this.server:8080

If your server has port 80 and 8443 open and you point a domain to your server, you can get a valid certificate automatially by opening the Nextcloud AIO Interface via:
https://your-domain-that-points-to-this-server.tld:8443"

exec "$@"
